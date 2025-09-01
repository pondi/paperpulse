<?php

namespace App\Jobs\Maintenance;

use App\Jobs\BaseJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteWorkingFiles extends BaseJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Delete Working Files';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            $metadata = $this->getMetadata();
            if (! $metadata) {
                // Try to get metadata from alternate cache keys as fallback
                $receiptMetadata = Cache::get("job.{$this->jobID}.receiptMetaData");
                if ($receiptMetadata) {
                    Log::warning('Using receipt metadata as fallback for file cleanup', [
                        'job_id' => $this->jobID,
                    ]);
                    // We can still clean up working files using the job ID pattern
                    $metadata = ['fileGuid' => null, 'jobName' => 'Receipt Processing'];
                } else {
                    // If no metadata exists, the files were likely already cleaned up
                    // This can happen if DeleteWorkingFiles runs multiple times due to race conditions
                    Log::info('No metadata found - files likely already cleaned up', [
                        'job_id' => $this->jobID,
                        'task_id' => $this->uuid,
                    ]);

                    // Still do a safe cleanup of any old temporary files
                    $this->performFallbackCleanup();

                    return;
                }
            }

            $fileGuid = $metadata['fileGuid'];
            $jobName = $metadata['jobName'] ?? $this->jobName;

            Log::info('Cleaning up working files', [
                'job_id' => $this->jobID,
                'file_guid' => $fileGuid,
            ]);

            $this->updateProgress(25);

            $deletedCount = 0;

            if ($fileGuid) {
                // Standard cleanup with known GUID
                $fileExtensions = ['.jpg', '.pdf'];

                foreach ($fileExtensions as $extension) {
                    $filePath = 'uploads/'.$fileGuid.$extension;

                    if (Storage::disk('local')->exists($filePath)) {
                        Storage::disk('local')->delete($filePath);
                        $deletedCount++;

                        Log::debug('Working file deleted', [
                            'job_id' => $this->jobID,
                            'file_path' => $filePath,
                            'file_guid' => $fileGuid,
                        ]);
                    }
                }
            } else {
                // Fallback: Clean up any working files that might be related to this job
                // This is less precise but ensures cleanup happens
                $uploadFiles = Storage::disk('local')->files('uploads');
                $now = time();

                foreach ($uploadFiles as $file) {
                    $fileTime = Storage::disk('local')->lastModified($file);

                    // Clean up files older than 1 hour (safety margin for any processing)
                    if ($now - $fileTime > 3600 &&
                        (str_ends_with($file, '.jpg') || str_ends_with($file, '.pdf'))) {
                        Storage::disk('local')->delete($file);
                        $deletedCount++;

                        Log::debug('Old working file cleaned up', [
                            'job_id' => $this->jobID,
                            'file_path' => $file,
                            'age_hours' => round(($now - $fileTime) / 3600, 2),
                        ]);
                    }
                }

                Log::info('Fallback cleanup completed', [
                    'job_id' => $this->jobID,
                    'files_cleaned' => $deletedCount,
                ]);
            }

            $this->updateProgress(75);

            // Clean up cache entries
            Cache::forget("job.{$this->jobID}.fileMetaData");
            Cache::forget("job.{$this->jobID}.receiptMetaData");

            $this->updateProgress(100);

            Log::info('Working files cleanup completed', [
                'job_id' => $this->jobID,
                'file_guid' => $fileGuid,
                'files_deleted' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Working files cleanup failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Perform safe fallback cleanup when no metadata exists
     */
    protected function performFallbackCleanup(): void
    {
        Log::info('Performing fallback cleanup of old working files', [
            'job_id' => $this->jobID,
        ]);

        $deletedCount = 0;
        $uploadFiles = Storage::disk('local')->files('uploads');
        $now = time();

        foreach ($uploadFiles as $file) {
            $fileTime = Storage::disk('local')->lastModified($file);

            // Clean up files older than 1 hour (safety margin)
            if ($now - $fileTime > 3600 &&
                (str_ends_with($file, '.jpg') || str_ends_with($file, '.JPG') || str_ends_with($file, '.pdf'))) {
                Storage::disk('local')->delete($file);
                $deletedCount++;

                Log::debug('Old working file cleaned up in fallback', [
                    'job_id' => $this->jobID,
                    'file_path' => $file,
                    'age_hours' => round(($now - $fileTime) / 3600, 2),
                ]);
            }
        }

        Log::info('Fallback cleanup completed', [
            'job_id' => $this->jobID,
            'files_cleaned' => $deletedCount,
        ]);

        // Clean up any remaining cache entries
        Cache::forget("job.{$this->jobID}.fileMetaData");
        Cache::forget("job.{$this->jobID}.receiptMetaData");
    }
}
