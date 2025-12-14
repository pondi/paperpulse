<?php

namespace App\Services\Files;

use App\Models\File;
use App\Services\Files\FileJobChainDispatcher;
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
    protected StorageService $storageService;

    protected FileJobChainDispatcher $jobChainDispatcher;

    public function __construct(
        StorageService $storageService,
        FileJobChainDispatcher $jobChainDispatcher
    ) {
        $this->storageService = $storageService;
        $this->jobChainDispatcher = $jobChainDispatcher;
    }

    /**
     * Reprocess a single file.
     *
     * @param  File  $file  The file to reprocess
     * @param  bool  $force  Force reprocessing even if already processing
     * @return array{success:bool,jobId:string,message:string}
     */
    public function reprocessFile(File $file, bool $force = false): array
    {
        // Validate file can be reprocessed
        $validation = $this->validateReprocessing($file, $force);
        if (! $validation['canReprocess']) {
            return [
                'success' => false,
                'jobId' => null,
                'message' => $validation['reason'],
            ];
        }

        try {
            Log::info('[FileReprocessing] Starting file reprocessing', [
                'file_id' => $file->id,
                'file_guid' => $file->guid,
                'file_type' => $file->file_type,
                'current_status' => $file->status,
                's3_path' => $file->s3_original_path,
            ]);

            // Generate new job ID for this reprocessing attempt
            $jobId = (string) Str::uuid();
            $jobName = 'Reprocess '.ucfirst($file->file_type);

            // Prepare metadata for job chain
            $metadata = $this->prepareReprocessingMetadata($file, $jobId, $jobName);

            // Store metadata for job chain
            JobMetadataPersistence::store($jobId, $metadata);

            // Create parent job history record
            JobHistoryCreator::createParentJob(
                $jobId,
                $jobName,
                $file->file_type,
                $metadata,
                $file->id,
                $file->fileName
            );

            // Reset file status to pending
            $file->status = 'pending';
            $file->save();

            // Dispatch job chain
            $this->jobChainDispatcher->dispatch($jobId, $file->file_type);

            Log::info('[FileReprocessing] File reprocessing initiated', [
                'file_id' => $file->id,
                'file_guid' => $file->guid,
                'job_id' => $jobId,
                'job_name' => $jobName,
            ]);

            return [
                'success' => true,
                'jobId' => $jobId,
                'message' => "File reprocessing started with job ID: {$jobId}",
            ];

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
     * Reprocess multiple files.
     *
     * @param  \Illuminate\Support\Collection  $files
     * @param  bool  $force
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
     * @param  File  $file
     * @param  bool  $force
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
     *
     * @param  File  $file
     * @param  string  $jobId
     * @param  string  $jobName
     * @return array
     */
    protected function prepareReprocessingMetadata(File $file, string $jobId, string $jobName): array
    {
        return [
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
            'metadata' => [
                'reprocessing' => true,
                'original_status' => $file->status,
                'reprocessed_at' => now()->toISOString(),
            ],
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
     *
     * @return array
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
