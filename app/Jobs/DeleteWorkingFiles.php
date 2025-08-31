<?php

namespace App\Jobs;

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
                throw new \Exception('No metadata found for job');
            }

            $fileGuid = $metadata['fileGuid'];
            $jobName = $metadata['jobName'] ?? $this->jobName;

            Log::info('Cleaning up working files', [
                'job_id' => $this->jobID,
                'file_guid' => $fileGuid,
            ]);

            $this->updateProgress(25);

            $fileExtensions = ['.jpg', '.pdf'];
            $deletedCount = 0;

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
}
