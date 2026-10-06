<?php

namespace App\Services\Files;

use App\Contracts\Services\FileStorageContract;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FileProcessingRequestService
{
    public function __construct(protected FileStorageContract $storage, protected FileJobChainDispatcher $dispatcher) {}

    public function process(FileProcessingRequest $request, ?string $sourceContent = null, ?FileJobChainDispatcher $dispatcher = null, ?FileStorageContract $storage = null): bool
    {
        $request->refresh();
        $file = File::withoutGlobalScope('user')->where('user_id', $request->user_id)->find($request->file_id);
        if (! $file || ! User::query()->whereKey($request->user_id)->exists()) {
            $request->update(['state' => 'cleanup_pending', 'last_error' => 'The owner or source file was deleted.']);
            FileCleanupManifest::query()->firstOrCreate(['file_id' => $request->file_id], [
                'user_id' => $request->user_id, 'reason' => 'account_delete',
                'objects' => [$request->original_path => ['path' => $request->original_path, 'done' => false]],
                'search_records' => [], 'available_at' => now()->subSecond(),
            ]);

            return false;
        }
        $storage ??= $this->storage;
        if ($request->state === 'upload_pending') {
            try {
                $alreadyStored = $sourceContent === null && $storage->existsInS3($request->storage_disk, $request->original_path);
                $content = $sourceContent;
                if ($content === null && ! $alreadyStored) {
                    if (! $request->working_path || ! is_file($request->working_path)) {
                        throw new RuntimeException('The tracked upload source is unavailable.');
                    }
                    $content = file_get_contents($request->working_path);
                    if ($content === false) {
                        throw new RuntimeException('The tracked upload source cannot be read.');
                    }
                }
                $path = $alreadyStored ? $request->original_path : $storage->storeToS3($content, $request->user_id, $request->guid, $request->file_type, 'original', $request->extension);
            } catch (Throwable $exception) {
                app(FileProcessingFailureReporter::class)->report($exception, 'upload-handoff', $file);
                $request->update(['state' => 'cleanup_pending', 'last_error' => $exception->getMessage()]);
                app(FileDeletionService::class)->deleteFile($file, $request->user_id);
                FileCleanupManifest::query()->where('file_id', $file->id)->update(['available_at' => now()->subSecond()]);

                return false;
            }
            try {
                $request->getConnection()->transaction(function () use ($request, $file, $path): void {
                    $request->update(['original_path' => $path, 'state' => 'pending', 'last_error' => null]);
                    $file->update(['s3_original_path' => $path]);
                    $metadata = JobMetadataPersistence::retrieve($request->job_id);
                    $metadata['s3OriginalPath'] = $path;
                    JobMetadataPersistence::store($request->job_id, $metadata);
                });
            } catch (Throwable $exception) {
                app(FileProcessingFailureReporter::class)->report($exception, 'upload-handoff', $file);
                Log::error('Upload remains tracked for recovery after persistence failure', ['request_id' => $request->id, 'error' => $exception->getMessage()]);

                return false;
            }
        }
        if ($request->state === 'dispatched') {
            return true;
        }
        $token = (string) Str::uuid();
        $claimed = FileProcessingRequest::query()->whereKey($request->id)->where('state', 'pending')->update([
            'state' => 'dispatching', 'claim_token' => $token, 'claimed_at' => now(), 'attempts' => $request->attempts + 1,
        ]);
        if (! $claimed) {
            return false;
        }
        try {
            ($dispatcher ?? $this->dispatcher)->dispatch($request->job_id, $request->file_type);
            FileProcessingRequest::query()->whereKey($request->id)->where('claim_token', $token)->update([
                'state' => 'dispatched', 'dispatched_at' => now(), 'last_error' => null,
            ]);
            if ($request->working_path && is_file($request->working_path) && $storage->deleteWorkingFile($request->working_path)) {
                $request->update(['working_path' => null]);
            }

            return true;
        } catch (Throwable $exception) {
            app(FileProcessingFailureReporter::class)->report($exception, 'upload-handoff', $file);
            FileProcessingRequest::query()->whereKey($request->id)->where('claim_token', $token)->update([
                'state' => 'pending', 'claim_token' => null, 'last_error' => $exception->getMessage(),
            ]);
            Log::warning('Accepted upload queue handoff remains pending', ['request_id' => $request->id, 'error' => $exception->getMessage()]);

            return false;
        }
    }

    public function recover(int $limit = 100): int
    {
        FileProcessingRequest::query()->where('state', 'dispatching')->where('claimed_at', '<', now()->subMinutes(10))
            ->each(function (FileProcessingRequest $request): void {
                $started = JobHistory::query()->where('parent_uuid', $request->job_id)->whereIn('status', ['processing', 'retrying', 'completed', 'failed', 'cancelled'])->exists();
                $request->update(['state' => $started ? 'dispatched' : 'pending', 'claim_token' => null]);
            });
        $recovered = 0;
        FileProcessingRequest::query()->whereIn('state', ['upload_pending', 'pending'])->orderBy('id')->limit($limit)->get()
            ->each(function (FileProcessingRequest $request) use (&$recovered): void {
                if ($this->process($request)) {
                    $recovered++;
                }
            });
        FileProcessingRequest::query()->where('state', 'cleanup_pending')->orderBy('id')->limit($limit)->get()
            ->each(function (FileProcessingRequest $request): void {
                $manifest = FileCleanupManifest::query()->where('file_id', $request->file_id)->first();
                if ($manifest) {
                    app(FileCleanupService::class)->process($manifest);
                    if ($manifest->fresh()->completed_at) {
                        if ($request->working_path && is_file($request->working_path)) {
                            $this->storage->deleteWorkingFile($request->working_path);
                        }
                        $request->update(['state' => 'abandoned', 'working_path' => null]);
                    }
                }
            });

        return $recovered;
    }
}
