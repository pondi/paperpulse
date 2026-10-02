<?php

namespace App\Services\Files;

use App\Models\File;
use App\Models\FileShare;
use App\Models\JobHistory;
use App\Services\Jobs\JobHistoryCreator;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\StorageService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles reprocessing of failed files.
 *
 * Since files are stored permanently in S3, we can safely reprocess any
 * failed file by starting a new job chain from scratch.
 */
class FileReprocessingService
{
    public function __construct(
        private StorageService $storageService,
        private FileJobChainDispatcher $jobChainDispatcher,
    ) {}

    /**
     * Reprocess a single file.
     *
     * @param  File  $file  The file to reprocess
     * @param  bool  $force  Force reprocessing even if already processing
     * @return array{success:bool,jobId:string,message:string}
     */
    public function reprocessFile(File $file, bool $force = false): array
    {
        try {
            return $file->getConnection()->transaction(function () use ($file, $force): array {
                $file = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
                $validation = $this->validateReprocessing($file, $force);
                if (! $validation['canReprocess']) {
                    return ['success' => false, 'jobId' => null, 'message' => $validation['reason']];
                }
                $jobId = (string) Str::uuid();
                $jobName = 'Reprocess '.ucfirst($file->file_type);
                $metadata = $this->prepareReprocessingMetadata($file, $jobId, $jobName);
                $file->update(['meta' => array_merge($file->meta ?? [], ['processing_generation' => (string) Str::uuid()]), 'status' => 'pending']);
                $metadata['processingGeneration'] = $file->meta['processing_generation'];
                JobMetadataPersistence::store($jobId, $metadata);
                JobHistoryCreator::createParentJob($jobId, $jobName, $file->file_type, $metadata, $file->id, $file->fileName);
                $this->jobChainDispatcher->dispatch($jobId, $file->file_type);

                return ['success' => true, 'jobId' => $jobId, 'message' => "File reprocessing started with job ID: {$jobId}"];
            });

        } catch (Exception $e) {
            Log::error('[FileReprocessing] Failed to start reprocessing', [
                'file_id' => $file->id,
                'file_guid' => $file->guid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'jobId' => null,
                'message' => "Failed to start reprocessing: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Change a file's type (receipt <-> document), move the original in storage, then reprocess.
     *
     * @return array{success:bool,jobId:?string,message:string}
     */
    public function changeTypeAndReprocess(File $file, string $newFileType, bool $force = false): array
    {
        if (! in_array($newFileType, ['receipt', 'document'], true)) {
            return [
                'success' => false,
                'jobId' => null,
                'message' => 'Invalid file type.',
            ];
        }

        if ($newFileType === $file->file_type) {
            return $this->reprocessFile($file, $force);
        }

        $moveResult = $this->changeFileTypeAndMoveStorage($file, $newFileType);
        if (! $moveResult['success']) {
            return $moveResult;
        }

        return $this->reprocessFile($file, $force);
    }

    /**
     * Reprocess multiple files.
     *
     * @param  \Illuminate\Support\Collection  $files
     * @return array{successful:int,failed:int,skipped:int,results:array}
     */
    public function reprocessFiles($files, bool $force = false): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'results' => [],
        ];

        foreach ($files as $file) {
            $result = $this->reprocessFile($file, $force);

            if ($result['success']) {
                $results['successful']++;
            } elseif (str_contains($result['message'], 'already')) {
                $results['skipped']++;
            } else {
                $results['failed']++;
            }

            $results['results'][] = [
                'file_id' => $file->id,
                'file_guid' => $file->guid,
                'file_name' => $file->fileName,
                'success' => $result['success'],
                'job_id' => $result['jobId'],
                'message' => $result['message'],
            ];

            // Small delay between dispatches to avoid overwhelming queue
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Validate if a file can be reprocessed.
     *
     * @return array{canReprocess:bool,reason:string|null}
     */
    protected function validateReprocessing(File $file, bool $force): array
    {
        // Check if S3 file exists
        if (empty($file->s3_original_path)) {
            return [
                'canReprocess' => false,
                'reason' => 'File has no S3 path - cannot reprocess',
            ];
        }

        // Verify S3 file exists
        try {
            $exists = $this->storageService->getFile($file->s3_original_path) !== null;
            if (! $exists) {
                return [
                    'canReprocess' => false,
                    'reason' => 'File not found in S3 storage',
                ];
            }
        } catch (Exception $e) {
            return [
                'canReprocess' => false,
                'reason' => "Cannot verify S3 file: {$e->getMessage()}",
            ];
        }

        // Check if already completed (unless forced)
        if (! $force && $file->status === 'completed') {
            return [
                'canReprocess' => false,
                'reason' => 'File already processed successfully (use --force to reprocess)',
            ];
        }

        // Check if currently processing (unless forced)
        if (! $force && $file->status === 'processing') {
            return [
                'canReprocess' => false,
                'reason' => 'File is currently being processed (use --force to restart)',
            ];
        }

        return [
            'canReprocess' => true,
            'reason' => null,
        ];
    }

    /**
     * Prepare metadata for reprocessing job chain.
     */
    protected function prepareReprocessingMetadata(File $file, string $jobId, string $jobName): array
    {
        $previousMetadata = JobHistory::query()->where('file_id', $file->id)
            ->whereNull('parent_uuid')->latest('id')->value('metadata') ?? [];

        return array_intersect_key($previousMetadata, array_flip(['processingProvider', 'pipeline'])) + [
            'fileId' => $file->id,
            'fileGuid' => $file->guid,
            'fileName' => $file->fileName,
            'filePath' => null, // Workers will download from S3
            'fileExtension' => $file->fileExtension,
            'fileSize' => $file->fileSize,
            'fileType' => $file->file_type,
            'userId' => $file->user_id,
            's3OriginalPath' => $file->s3_original_path,
            'jobName' => $jobName,
            'fileCreatedAt' => $file->file_created_at?->toISOString(),
            'fileModifiedAt' => $file->file_modified_at?->toISOString(),
            'metadata' => [
                'reprocessing' => true,
                'original_status' => $file->status,
                'reprocessed_at' => now()->toISOString(),
                // Preserve original file dates - these were extracted from file metadata
                // during initial upload before the file was stored in S3. We must not
                // re-extract these during reprocessing as the S3 file will have
                // different timestamps than the original file.
                'file_created_at' => $file->file_created_at?->toISOString(),
                'file_modified_at' => $file->file_modified_at?->toISOString(),
            ],
        ];
    }

    /**
     * Move the original S3 object to the correct type folder and update DB fields.
     *
     * @return array{success:bool,jobId:?string,message:string}
     */
    protected function changeFileTypeAndMoveStorage(File $file, string $newFileType): array
    {
        if (empty($file->s3_original_path)) {
            return [
                'success' => false,
                'jobId' => null,
                'message' => 'File has no stored original path.',
            ];
        }

        $parsed = $this->parseStoragePath($file->s3_original_path);
        $extension = $parsed['extension'] ?? $file->fileExtension ?? 'pdf';

        $newOriginalPath = StoragePathBuilder::storagePath(
            $file->user_id,
            $file->guid,
            $newFileType,
            'original',
            $extension
        );

        try {
            $this->storageService->moveWithinStorage($file->s3_original_path, $newOriginalPath);
        } catch (Exception $e) {
            Log::error('[FileReprocessing] Failed to move original file during type change', [
                'file_id' => $file->id,
                'from' => $file->s3_original_path,
                'to' => $newOriginalPath,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'jobId' => null,
                'message' => 'Failed to move original file in storage: '.$e->getMessage(),
            ];
        }

        // Clear any derived outputs (they will be regenerated)
        $this->resetFileProcessingState($file);

        // Entity cleanup happens in reprocessFile() which is called after this method
        // No need to delete entities here - reprocessFile() handles it

        FileShare::where('file_id', $file->id)->update(['file_type' => $newFileType]);

        $file->file_type = $newFileType;
        $file->processing_type = $newFileType;
        $file->s3_original_path = $newOriginalPath;
        $file->save();

        return [
            'success' => true,
            'jobId' => null,
            'message' => 'File type updated.',
        ];
    }

    /**
     * Parse canonical storage paths like: receipts/{userId}/{guid}/{variant}.{extension}
     *
     * @return array{folder:string,user_id:int,guid:string,variant:string,extension:string}|null
     */
    protected function parseStoragePath(string $path): ?array
    {
        $pattern = '#^(receipts|documents)/(\\d+)/([^/]+)/([^/]+)\\.([A-Za-z0-9]+)$#';

        if (! preg_match($pattern, $path, $matches)) {
            return null;
        }

        return [
            'folder' => $matches[1],
            'user_id' => (int) $matches[2],
            'guid' => $matches[3],
            'variant' => $matches[4],
            'extension' => $matches[5],
        ];
    }

    /**
     * Find files that can be reprocessed.
     *
     * @param  string|null  $fileType  Filter by file type ('receipt' or 'document')
     * @param  string|null  $status  Filter by status (default: 'failed')
     * @return Collection
     */
    public function findReprocessableFiles(?string $fileType = null, ?string $status = 'failed')
    {
        $query = File::whereNotNull('s3_original_path');

        if ($status) {
            $query->where('status', $status);
        }

        if ($fileType) {
            $query->where('file_type', $fileType);
        }

        return $query->with('user')->get();
    }

    /**
     * Get statistics about reprocessable files.
     */
    public function getReprocessingStats(): array
    {
        return [
            'failed_receipts' => File::where('status', 'failed')
                ->where('file_type', 'receipt')
                ->whereNotNull('s3_original_path')
                ->count(),
            'failed_documents' => File::where('status', 'failed')
                ->where('file_type', 'document')
                ->whereNotNull('s3_original_path')
                ->count(),
            'pending_receipts' => File::where('status', 'pending')
                ->where('file_type', 'receipt')
                ->whereNotNull('s3_original_path')
                ->count(),
            'pending_documents' => File::where('status', 'pending')
                ->where('file_type', 'document')
                ->whereNotNull('s3_original_path')
                ->count(),
            'processing_receipts' => File::where('status', 'processing')
                ->where('file_type', 'receipt')
                ->whereNotNull('s3_original_path')
                ->count(),
            'processing_documents' => File::where('status', 'processing')
                ->where('file_type', 'document')
                ->whereNotNull('s3_original_path')
                ->count(),
        ];
    }
}
