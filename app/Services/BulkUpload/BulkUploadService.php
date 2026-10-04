<?php

declare(strict_types=1);

namespace App\Services\BulkUpload;

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Services\File\FileValidationService;
use App\Services\FileProcessingService;
use App\Services\Files\FileUploadConfigService;
use App\Services\Jobs\JobParentStatusCalculator;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates bulk upload sessions: creation, file tracking,
 * confirmation, and integration with the existing processing pipeline.
 */
class BulkUploadService
{
    public function __construct(
        private FileProcessingService $fileProcessingService,
        private BulkPresignService $presignService,
    ) {}

    /**
     * Create a new bulk upload session with file manifest.
     *
     * @param  array{file_type: string, collection_ids?: array, tag_ids?: array, note?: string}  $defaults
     * @param  array<array{filename: string, path?: string, size: int, hash: string, extension: string, mime_type: string, file_type?: string, collection_ids?: array, tag_ids?: array, note?: string}>  $files
     * @return array{session: BulkUploadSession, files: Collection}
     */
    public function createSession(int $userId, array $defaults, array $files): array
    {
        $sessionUuid = (string) Str::uuid();

        return DB::transaction(function () use ($userId, $sessionUuid, $defaults, $files) {
            $session = BulkUploadSession::create([
                'uuid' => $sessionUuid,
                'user_id' => $userId,
                'status' => BulkUploadSessionStatus::Pending,
                'total_files' => count($files),
                'default_file_type' => $defaults['file_type'],
                'default_collection_ids' => $defaults['collection_ids'] ?? null,
                'default_tag_ids' => $defaults['tag_ids'] ?? null,
                'default_note' => $defaults['note'] ?? null,
                'expires_at' => now()->addHours(24),
            ]);

            // Normalize all hashes upfront
            $normalizedFiles = array_map(function (array $fileData): array {
                $fileData['_normalized_hash'] = $this->normalizeHash($fileData['hash']);

                return $fileData;
            }, $files);

            // Batch dedup check: single query for all hashes instead of N queries
            $allHashes = array_column($normalizedFiles, '_normalized_hash');
            $existingFiles = File::withoutGlobalScope('user')
                ->where('user_id', $userId)
                ->whereIn('file_hash', $allHashes)
                ->deduplicatable()
                ->orderByDesc('id')
                ->get()
                ->keyBy('file_hash');

            // Build all rows for batch insert
            $now = now();
            $duplicateCount = 0;
            $insertRows = [];
            $fileUuids = [];
            $manifestFiles = [];

            foreach ($normalizedFiles as $fileData) {
                $fileUuid = (string) Str::uuid();
                $hash = $fileData['_normalized_hash'];
                $existingFile = $existingFiles->get($hash);
                $manifestFileUuid = $manifestFiles[$hash] ?? null;
                $isDuplicate = $existingFile !== null || $manifestFileUuid !== null;
                $manifestFiles[$hash] ??= $fileUuid;

                if ($isDuplicate) {
                    $duplicateCount++;
                }

                $fileUuids[] = $fileUuid;
                $insertRows[] = [
                    'uuid' => $fileUuid,
                    'bulk_upload_session_id' => $session->id,
                    'user_id' => $userId,
                    'original_filename' => $fileData['filename'],
                    'original_path' => $fileData['path'] ?? null,
                    'file_size' => $fileData['size'],
                    'file_hash' => $hash,
                    'file_extension' => strtolower($fileData['extension']),
                    'mime_type' => $fileData['mime_type'],
                    'status' => $isDuplicate ? BulkUploadFileStatus::Duplicate->value : BulkUploadFileStatus::Pending->value,
                    'file_type' => $fileData['file_type'] ?? null,
                    'collection_ids' => isset($fileData['collection_ids']) ? json_encode($fileData['collection_ids']) : null,
                    'tag_ids' => isset($fileData['tag_ids']) ? json_encode($fileData['tag_ids']) : null,
                    'note' => $fileData['note'] ?? null,
                    's3_key' => $this->buildS3Key($userId, $sessionUuid, $fileUuid, $fileData['extension']),
                    'presigned_expires_at' => null,
                    'file_id' => $existingFile?->id,
                    'job_id' => null,
                    'error_message' => $existingFile
                        ? "Duplicate of file ID {$existingFile->id} (guid: {$existingFile->guid})"
                        : ($manifestFileUuid ? "Duplicate of manifest file {$manifestFileUuid}" : null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Batch insert in chunks of 500 to avoid query size limits
            foreach (array_chunk($insertRows, 500) as $chunk) {
                BulkUploadFile::insert($chunk);
            }

            $session->update(['duplicate_count' => $duplicateCount]);

            // Fetch the created records for the response
            $bulkFiles = BulkUploadFile::where('bulk_upload_session_id', $session->id)
                ->whereIn('uuid', $fileUuids)
                ->get();

            Log::info('[BulkUpload] Session created', [
                'session_uuid' => $sessionUuid,
                'user_id' => $userId,
                'total_files' => count($files),
                'duplicates' => $duplicateCount,
            ]);

            return ['session' => $session->fresh(), 'files' => $bulkFiles];
        });
    }

    /**
     * Generate presigned PUT URLs for a batch of files.
     *
     * @param  array<string>  $fileUuids
     * @return array<array{uuid: string, url: string, expires_at: string, headers: array}>
     */
    public function presignFiles(BulkUploadSession $session, array $fileUuids): array
    {
        return $session->getConnection()->transaction(function () use ($session, $fileUuids): array {
            $session = BulkUploadSession::withoutGlobalScope('user')->where('user_id', $session->user_id)->lockForUpdate()->findOrFail($session->id);
            $this->ensureSessionActive($session);
            $presigned = [];
            foreach ($session->files()->withoutGlobalScope('user')->where('user_id', $session->user_id)->whereIn('uuid', $fileUuids)->lockForUpdate()->get() as $file) {
                if (! $file->status->canPresign() || $file->file_id !== null) {
                    continue;
                }
                $result = $this->presignService->generatePutUrl($file->s3_key, $file->mime_type);
                $file->update(['status' => BulkUploadFileStatus::Presigned, 'presigned_expires_at' => $result['expires_at'], 'error_message' => null]);
                $presigned[] = ['uuid' => $file->uuid, 'url' => $result['url'], 'expires_at' => $result['expires_at']->toIso8601String(), 'headers' => $result['headers']];
            }
            if ($session->status === BulkUploadSessionStatus::Pending) {
                $session->update(['status' => BulkUploadSessionStatus::Uploading]);
            }
            $session->update(['cleanup_pending' => true]);
            $session->extendExpiry();

            return $presigned;
        });
    }

    /** @return array{uuid: string, url: string, expires_at: string, headers: array} */
    public function presignSingleFile(BulkUploadSession $session, BulkUploadFile $file): array
    {
        $result = $this->presignFiles($session, [$file->uuid]);
        if ($result === []) {
            throw new Exception('File cannot be presigned in its current state.');
        }

        return $result[0];
    }

    /**
     * Confirm a file upload and trigger processing.
     *
     * Downloads from uplink-incoming, feeds into the standard FileProcessingService
     * pipeline, which handles S3 move, DB record, and job dispatch.
     *
     * @return array{file_id: int, file_guid: string, job_id: string}
     */
    public function confirmFile(BulkUploadSession $session, BulkUploadFile $bulkFile): array
    {
        try {
            $result = $session->getConnection()->transaction(function () use ($session, $bulkFile): array {
                $session = BulkUploadSession::withoutGlobalScope('user')->where('user_id', $session->user_id)->lockForUpdate()->findOrFail($session->id);
                $bulkFile = $session->files()->withoutGlobalScope('user')->where('user_id', $session->user_id)->lockForUpdate()->findOrFail($bulkFile->id);
                if ($bulkFile->file_id !== null && $bulkFile->job_id !== null) {
                    $file = File::withoutGlobalScope('user')->withTrashed()->where('user_id', $session->user_id)->findOrFail($bulkFile->file_id);

                    return ['file_id' => $file->id, 'file_guid' => $file->guid, 'job_id' => $bulkFile->job_id];
                }
                $this->ensureSessionActive($session);
                if (! $bulkFile->status->canConfirm()) {
                    throw new Exception('File cannot be confirmed in its current state.');
                }
                $bulkFile->update(['status' => BulkUploadFileStatus::Confirming]);
                $disk = Storage::disk('uplink');
                $maximum = app(FileUploadConfigService::class)->getMaxSizeBytes($bulkFile->getEffectiveFileType(), $bulkFile->file_extension);
                $size = $disk->size($bulkFile->s3_key);
                if ($size > $maximum || $size !== $bulkFile->file_size) {
                    throw new Exception('Uploaded object exceeds the processing limit or differs from its declared size.');
                }
                $stream = $disk->readStream($bulkFile->s3_key);
                if (! is_resource($stream)) {
                    throw new Exception('Uploaded object could not be read.');
                }
                try {
                    $content = stream_get_contents($stream, $maximum + 1);
                } finally {
                    fclose($stream);
                }
                if ($content === false || strlen($content) !== $size || ! hash_equals($bulkFile->file_hash, hash('sha256', $content))) {
                    throw new Exception('Uploaded object size or checksum does not match its manifest.');
                }
                $fileData = [
                    'content' => $content, 'fileName' => $bulkFile->original_filename,
                    'extension' => $bulkFile->file_extension, 'size' => $size,
                    'mimeType' => $bulkFile->mime_type, 'source' => 'uplink',
                ];
                $validation = app(FileValidationService::class)->validateFileData($fileData, $bulkFile->getEffectiveFileType());
                if (! $validation['valid']) {
                    throw new Exception(implode(', ', $validation['errors']));
                }
                $processed = $this->fileProcessingService->processFile($fileData, $bulkFile->getEffectiveFileType(), $session->user_id, [
                    'source' => 'uplink', 'collection_ids' => $bulkFile->getEffectiveCollectionIds(),
                    'tag_ids' => $bulkFile->getEffectiveTagIds(), 'note' => $bulkFile->getEffectiveNote(),
                    'bulkUploadSessionId' => $session->uuid, 'bulkUploadFileId' => $bulkFile->uuid,
                ]);
                $bulkFile->update(['status' => BulkUploadFileStatus::Processing, 'file_id' => $processed['fileId'], 'job_id' => $processed['jobId'], 'error_message' => null]);
                $session->refreshCounts();
                $session->extendExpiry();

                return ['file_id' => $processed['fileId'], 'file_guid' => $processed['fileGuid'], 'job_id' => $processed['jobId']];
            });
        } catch (Exception $exception) {
            $session->getConnection()->transaction(function () use ($session, $bulkFile, $exception): void {
                $session = BulkUploadSession::withoutGlobalScope('user')->where('user_id', $session->user_id)->lockForUpdate()->findOrFail($session->id);
                $session->files()->withoutGlobalScope('user')->whereKey($bulkFile->id)->whereNull('file_id')
                    ->whereIn('status', [BulkUploadFileStatus::Presigned, BulkUploadFileStatus::Uploading, BulkUploadFileStatus::Uploaded, BulkUploadFileStatus::Confirming, BulkUploadFileStatus::Failed])
                    ->update(['status' => BulkUploadFileStatus::Failed, 'error_message' => $exception->getMessage()]);
                $session->refreshCounts();
            });
            throw $exception;
        }
        try {
            Storage::disk('uplink')->delete($bulkFile->s3_key);
        } catch (Exception $exception) {
            Log::warning('Bulk source cleanup failed', ['file_uuid' => $bulkFile->uuid, 'error' => $exception->getMessage()]);
        }

        return $result;
    }

    public function cancelSession(BulkUploadSession $session): void
    {
        $session->getConnection()->transaction(function () use ($session): void {
            $session = BulkUploadSession::withoutGlobalScope('user')->where('user_id', $session->user_id)->lockForUpdate()->findOrFail($session->id);
            $this->ensureSessionActive($session);
            $session->update(['status' => BulkUploadSessionStatus::Cancelled]);
            foreach ($session->files()->withoutGlobalScope('user')->whereNull('file_id')->where('status', '!=', BulkUploadFileStatus::Duplicate)->lockForUpdate()->get() as $file) {
                $file->update(['status' => BulkUploadFileStatus::Skipped]);
                try {
                    Storage::disk('uplink')->delete($file->s3_key);
                } catch (Exception $exception) {
                    Log::warning('Bulk cancellation cleanup failed', ['file_uuid' => $file->uuid, 'error' => $exception->getMessage()]);
                }
            }
            $session->refreshCounts();
        });
    }

    public function cleanupSession(BulkUploadSession $session): bool
    {
        return $session->getConnection()->transaction(function () use ($session): bool {
            $session = BulkUploadSession::withoutGlobalScope('user')->lockForUpdate()->findOrFail($session->id);
            if (! $session->cleanup_pending || $session->isActive()) {
                return false;
            }
            $lastGrant = $session->files()->withoutGlobalScope('user')->max('presigned_expires_at');
            if ($lastGrant !== null && now()->lt($lastGrant)) {
                return false;
            }
            if (! in_array($session->status, [BulkUploadSessionStatus::Cancelled, BulkUploadSessionStatus::Completed, BulkUploadSessionStatus::Failed], true)) {
                $session->update(['status' => BulkUploadSessionStatus::Cancelled]);
            }
            $session->files()->withoutGlobalScope('user')->whereNull('file_id')->where('status', '!=', BulkUploadFileStatus::Duplicate)->update(['status' => BulkUploadFileStatus::Skipped]);
            $protected = $session->files()->withoutGlobalScope('user')->whereIn('job_id', FileProcessingRequest::query()->where('state', 'upload_pending')->selectRaw('CAST(job_id AS TEXT)'))->pluck('s3_key')->all();
            $prefix = rtrim(config('filesystems.uplink_prefix', 'uplink-incoming/'), '/').'/'.$session->user_id.'/'.$session->uuid.'/';
            $disk = Storage::disk('uplink');
            foreach ($disk->getDriver()->listContents($prefix, true) as $object) {
                if ($object->isFile() && ! in_array($object->path(), $protected, true) && ! $disk->delete($object->path())) {
                    throw new RuntimeException('Bulk source deletion was not acknowledged.');
                }
            }
            $session->update(['cleanup_pending' => $protected !== []]);
            $session->checkCompletion();

            return ! $session->cleanup_pending;
        });
    }

    public function reconcileJob(string $jobId): void
    {
        foreach (BulkUploadFile::withoutGlobalScope('user')->where('job_id', $jobId)->get() as $bulkFile) {
            $this->reconcileFile($bulkFile);
        }
    }

    public function reconcileSession(BulkUploadSession $session): void
    {
        $session->files()->withoutGlobalScope('user')->whereNotNull('job_id')->whereNotNull('file_id')
            ->whereIn('status', [BulkUploadFileStatus::Processing, BulkUploadFileStatus::Failed])
            ->chunkById(100, function ($files): void {
                foreach ($files as $file) {
                    $this->reconcileFile($file);
                }
            });
        $session->refresh()->checkCompletion();
    }

    private function reconcileFile(BulkUploadFile $bulkFile): void
    {
        $bulkFile->getConnection()->transaction(function () use ($bulkFile): void {
            $session = BulkUploadSession::withoutGlobalScope('user')->lockForUpdate()->findOrFail($bulkFile->bulk_upload_session_id);
            $bulkFile = $session->files()->withoutGlobalScope('user')->lockForUpdate()->findOrFail($bulkFile->id);
            $job = JobHistory::query()->where('uuid', $bulkFile->job_id)->where('file_id', $bulkFile->file_id)->with('tasks')->first();
            $file = File::withoutGlobalScope('user')->where('user_id', $bulkFile->user_id)->find($bulkFile->file_id);
            if ($job === null) {
                return;
            }
            $status = JobParentStatusCalculator::calculate($job);
            if ($file === null || $status === 'failed' || $file->status === 'failed') {
                $bulkFile->update(['status' => BulkUploadFileStatus::Failed, 'error_message' => $job->tasks->firstWhere('status', 'failed')?->exception ?? $file?->meta['last_processing_error']['message'] ?? 'Processing source is unavailable or failed.']);
            } elseif ($status === 'completed' && in_array($file->status, ['completed', 'needs_review'], true)) {
                $bulkFile->update(['status' => BulkUploadFileStatus::Completed, 'error_message' => null]);
            }
            $session->checkCompletion();
        });
    }

    /**
     * Get session with summary for status endpoint.
     *
     * @return array{session: BulkUploadSession, summary: array}
     */
    public function getSessionStatus(BulkUploadSession $session): array
    {
        $this->reconcileSession($session);

        $filesByStatus = $session->files()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'session' => $session,
            'summary' => [
                'total_files' => $session->total_files,
                'uploaded_count' => $session->uploaded_count,
                'completed_count' => $session->completed_count,
                'failed_count' => $session->failed_count,
                'duplicate_count' => $session->duplicate_count,
                'by_status' => $filesByStatus,
                'is_complete' => $session->completed_at !== null,
                'is_expired' => $session->isExpired(),
            ],
        ];
    }

    private function buildS3Key(int $userId, string $sessionUuid, string $fileUuid, string $extension): string
    {
        $prefix = config('filesystems.uplink_prefix', 'uplink-incoming/');

        return trim("{$prefix}{$userId}/{$sessionUuid}/{$fileUuid}.{$extension}", '/');
    }

    private function normalizeHash(string $hash): string
    {
        // Strip optional "sha256:" prefix
        if (str_starts_with($hash, 'sha256:')) {
            return substr($hash, 7);
        }

        return $hash;
    }

    private function ensureSessionActive(BulkUploadSession $session): void
    {
        if ($session->isExpired()) {
            throw new Exception('Upload session has expired');
        }

        if (! $session->isActive()) {
            throw new Exception("Session is not active (status: {$session->status->value})");
        }
    }
}
