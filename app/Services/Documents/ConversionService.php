<?php

namespace App\Services\Documents;

use App\Contracts\DocumentConverter;
use App\Jobs\Files\ConvertOfficeFile;
use App\Models\File;
use App\Models\FileConversion;
use App\Models\JobHistory;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use Illuminate\Support\Facades\File as LocalFiles;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class ConversionService
{
    public function __construct(private StorageService $storage) {}

    public function requiresConversion(string $extension): bool
    {
        return isset(ConversionCapabilities::OFFICE_MIME_TYPES[strtolower($extension)]);
    }

    public function queueConversion(File $file, string $inputS3Path, string $outputS3Path, bool $dispatch = true): FileConversion
    {
        $this->assertEnabled();
        if (! $this->requiresConversion($file->fileExtension ?? '')) {
            throw new RuntimeException('Unsupported conversion extension.');
        }
        if ($inputS3Path !== $file->s3_original_path || $inputS3Path === $outputS3Path) {
            throw new RuntimeException('Conversion must use the owned original and a separate archive path.');
        }
        $this->driver();

        return $file->getConnection()->transaction(function () use ($file, $inputS3Path, $outputS3Path, $dispatch): FileConversion {
            $owned = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
            $generation = $owned->meta['processing_generation'] ?? (string) Str::uuid();
            $owned->update(['meta' => array_merge($owned->meta ?? [], ['processing_generation' => $generation])]);
            $existing = FileConversion::where('file_id', $owned->id)
                ->where('user_id', $owned->user_id)->whereIn('status', ['pending', 'processing'])
                ->where('metadata->generation', $generation)->first();
            if ($existing !== null) {
                return $existing;
            }
            $conversion = FileConversion::create([
                'file_id' => $owned->id,
                'user_id' => $owned->user_id,
                'status' => 'pending',
                'input_extension' => strtolower($owned->fileExtension),
                'input_s3_path' => $inputS3Path,
                'output_s3_path' => $outputS3Path,
                'retry_count' => 0,
                'max_retries' => config('processing.conversion.max_retries', 3),
                'metadata' => ['driver' => config('processing.conversion.driver'), 'generation' => $generation],
            ]);
            if ($dispatch) {
                ConvertOfficeFile::dispatch($conversion->id)->onQueue('conversions')->afterCommit();
            }

            return $conversion;
        });
    }

    public function convert(FileConversion $conversion): void
    {
        $claim = $this->claim($conversion);
        if ($claim === null) {
            return;
        }
        $directory = null;
        try {
            $this->assertEnabled();
            $file = File::withoutGlobalScope('user')->where('user_id', $conversion->user_id)->findOrFail($conversion->file_id);
            if (($file->meta['processing_generation'] ?? 'initial') !== ($conversion->metadata['generation'] ?? 'initial')) {
                throw new RuntimeException('Conversion belongs to an obsolete processing generation.');
            }
            $driver = $this->driver($conversion->metadata['driver'] ?? null);
            $directory = storage_path('app/private/conversions/'.Str::uuid());
            LocalFiles::ensureDirectoryExists($directory, 0700);
            $source = $directory.'/source.'.$conversion->input_extension;
            $output = $directory.'/archive.pdf';
            $content = $this->storage->getFile($conversion->input_s3_path);
            if ($content === null || strlen($content) > config('processing.conversion.max_input_bytes', 20971520)
                || file_put_contents($source, $content) !== strlen($content)) {
                throw new RuntimeException('Conversion source is missing, too large, or could not be staged.');
            }
            $mime = ConversionCapabilities::detect($source, $conversion->input_extension);
            $driver->convert($source, $output, $mime);
            if (! is_file($output) || filesize($output) < 8
                || filesize($output) > config('processing.conversion.max_output_bytes', 104857600)
                || file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
                throw new RuntimeException('Conversion did not produce a valid nonempty PDF.');
            }
            try {
                if (count((new Parser)->parseFile($output)->getPages()) === 0) {
                    throw new RuntimeException('PDF has no pages.');
                }
            } catch (Throwable $exception) {
                throw new RuntimeException('Conversion did not produce a valid nonempty PDF.', previous: $exception);
            }
            $file->getConnection()->transaction(function () use ($file, $conversion, $output, $claim): void {
                $locked = File::withoutGlobalScope('user')->where('user_id', $conversion->user_id)->lockForUpdate()->findOrFail($file->id);
                $request = FileConversion::query()->lockForUpdate()->findOrFail($conversion->id);
                if ($locked->trashed() || ($locked->meta['processing_generation'] ?? 'initial') !== ($request->metadata['generation'] ?? 'initial')
                    || ($request->metadata['claim_token'] ?? null) !== $claim) {
                    throw new RuntimeException('The file or conversion generation changed while conversion was running.');
                }
                $expected = StoragePathBuilder::storagePath($locked->user_id, $locked->guid, $locked->file_type ?? 'document', 'archive', 'pdf');
                if ($expected !== $request->output_s3_path) {
                    throw new RuntimeException('Conversion archive path does not match its durable request.');
                }
                $path = $this->storage->storeFile(file_get_contents($output), $locked->user_id, $locked->guid, $locked->file_type ?? 'document', 'archive', 'pdf');
                if ($path !== $expected) {
                    throw new RuntimeException('Storage did not acknowledge the canonical archive path.');
                }
                $locked->update(['s3_archive_path' => $path]);
                $request->markAsCompleted($path);
            });
        } catch (Throwable $exception) {
            $conversion->getConnection()->transaction(function () use ($conversion, $claim, $exception): void {
                $locked = FileConversion::query()->lockForUpdate()->findOrFail($conversion->id);
                if (($locked->metadata['claim_token'] ?? null) === $claim) {
                    $locked->markAsFailed('Office conversion failed ('.class_basename($exception).').');
                }
            });
            throw $exception;
        } finally {
            if ($directory !== null) {
                LocalFiles::deleteDirectory($directory);
            }
        }
    }

    /** @return array{success: bool, output_path?: string, error?: string} */
    public function waitForCompletion(FileConversion $conversion, int $timeoutSeconds = 120): array
    {
        $conversion->refresh();

        return $conversion->isCompleted()
            ? ['success' => true, 'output_path' => $conversion->output_s3_path]
            : ['success' => false, 'error' => $conversion->error_message ?? 'Conversion has not completed; extraction must wait for its queued callback.'];
    }

    public function getStatus(int $conversionId): ?string
    {
        return FileConversion::find($conversionId)?->status;
    }

    public function prepareResume(FileConversion $conversion, string $chainId, array $remainingSteps, ?string $originStepUuid = null): ConvertOfficeFile
    {
        $metadata = $conversion->metadata ?? [];
        if (isset($metadata['resume_payload'])) {
            $step = unserialize(base64_decode($metadata['resume_payload'], true), ['allowed_classes' => [ConvertOfficeFile::class]]);
            if (! $step instanceof ConvertOfficeFile || $step->jobID !== $chainId) {
                throw new RuntimeException('Conversion already belongs to another processing chain.');
            }

            return $step;
        }
        $step = (new ConvertOfficeFile($conversion->id, $chainId))->onQueue('conversions');
        $step->chained = $remainingSteps;
        $metadata['resume_payload'] = base64_encode(serialize($step));
        $metadata['chain_id'] = $chainId;
        $metadata['origin_step_uuid'] = $originStepUuid;
        $conversion->update(['metadata' => $metadata]);

        return $step;
    }

    public function retry(FileConversion $conversion): bool
    {
        return $conversion->getConnection()->transaction(function () use ($conversion): bool {
            $locked = FileConversion::query()->lockForUpdate()->findOrFail($conversion->id);
            if (! $locked->hasFailed() || ! $locked->canRetry()) {
                return false;
            }
            $locked->incrementRetryCount();
            $locked->update(['status' => 'pending', 'error_message' => null, 'completed_at' => null]);
            $payload = $locked->metadata['resume_payload'] ?? null;
            $job = $payload ? unserialize(base64_decode($payload, true), ['allowed_classes' => [ConvertOfficeFile::class]]) : new ConvertOfficeFile($locked->id);
            if (! $job instanceof ConvertOfficeFile) {
                throw new RuntimeException('Conversion has an invalid resume payload.');
            }
            if ($payload) {
                JobHistory::query()->where('uuid', $job->uuid)->update(['status' => 'pending', 'exception' => null, 'finished_at' => null]);
                JobHistory::query()->where('parent_uuid', $job->jobID)->where('status', 'cancelled')->update(['status' => 'pending']);
                JobHistory::query()->where('uuid', $job->jobID)->update(['status' => 'pending']);
            }
            dispatch($job->onQueue('conversions')->afterCommit());

            return true;
        });
    }

    private function claim(FileConversion $conversion): ?string
    {
        return $conversion->getConnection()->transaction(function () use ($conversion): ?string {
            $locked = FileConversion::query()->lockForUpdate()->findOrFail($conversion->id);
            if ($locked->isCompleted()) {
                $conversion->refresh();

                return null;
            }
            if ($locked->isProcessing() && ($locked->metadata['claim_until'] ?? '') > now()->toISOString()) {
                throw new RuntimeException('Conversion already has an active execution claim.');
            }
            $token = (string) Str::uuid();
            $locked->update(['status' => 'processing', 'started_at' => now(), 'metadata' => array_merge($locked->metadata ?? [], [
                'claim_token' => $token, 'claim_until' => now()->addSeconds(310)->toISOString(),
            ])]);
            $conversion->refresh();

            return $token;
        });
    }

    public function reconcileAbandoned(int $limit = 100): int
    {
        $requeued = 0;
        $requests = FileConversion::query()->whereIn('status', ['pending', 'processing'])->where('updated_at', '<', now()->subMinutes(6))->limit($limit)->get();
        foreach ($requests as $request) {
            $recovered = $request->getConnection()->transaction(function () use ($request): bool {
                $locked = FileConversion::query()->lockForUpdate()->findOrFail($request->id);
                if (! in_array($locked->status, ['pending', 'processing'], true)
                    || $locked->updated_at >= now()->subMinutes(6)
                    || ($locked->metadata['claim_until'] ?? '') > now()->toISOString()) {
                    return false;
                }
                $locked->markAsFailed('Conversion handoff or execution was abandoned.');

                return $this->retry($locked);
            });
            $requeued += (int) $recovered;
        }

        return $requeued;
    }

    private function assertEnabled(): void
    {
        if (! config('processing.conversion.enabled')) {
            throw new RuntimeException('Office conversion is disabled.');
        }
    }

    private function driver(?string $name = null): DocumentConverter
    {
        return match ($name ?? config('processing.conversion.driver')) {
            'local' => app(LocalOfficeConverter::class),
            'external' => app(GotenbergConverter::class),
            default => throw new RuntimeException('Unsupported office conversion driver.'),
        };
    }
}
