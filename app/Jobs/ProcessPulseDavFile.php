<?php

namespace App\Jobs;

use App\Models\PulseDavFile;
use App\Services\FileProcessingService;
use App\Services\PulseDavService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPulseDavFile extends BaseJob
{
    public $timeout = 3600;

    public $tries = 5;

    public $backoff = 10;

    protected $pulseDavFile;

    protected $tagIds;

    /**
     * Create a new job instance.
     */
    public function __construct(PulseDavFile $pulseDavFile, array $tagIds = [])
    {
        // Generate a unique job ID for this processing chain
        $jobID = (string) Str::uuid();
        parent::__construct($jobID);

        $this->pulseDavFile = $pulseDavFile;
        $this->tagIds = $tagIds;
        $this->jobName = 'Process PulseDav File';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        $pulseDavService = app(PulseDavService::class);
        $fileProcessingService = app(FileProcessingService::class);

        try {
            Log::info('[ProcessPulseDavFile] Job started', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                's3_path' => $this->pulseDavFile->s3_path,
                'file_type' => $this->pulseDavFile->file_type,
                'tag_ids' => $this->tagIds,
                'filename' => $this->pulseDavFile->filename,
                'status' => $this->pulseDavFile->status,
            ]);

            // Mark as processing
            $this->pulseDavFile->markAsProcessing();

            Log::info('[ProcessPulseDavFile] Calling FileProcessingService', [
                'method' => 'processPulseDavFile',
                's3_path' => $this->pulseDavFile->s3_path,
                'user_id' => $this->pulseDavFile->user_id,
            ]);

            // Use the unified FileProcessingService to process the file
            $result = $fileProcessingService->processPulseDavFile(
                $this->pulseDavFile->s3_path,
                $this->pulseDavFile->file_type ?? 'receipt',
                $this->pulseDavFile->user_id,
                [
                    'tagIds' => $this->tagIds,
                    'pulseDavFileId' => $this->pulseDavFile->id,
                    'source' => 'pulsedav',
                    'originalFilename' => $this->pulseDavFile->filename,
                    'jobId' => $this->jobID, // Pass the parent job ID
                ]
            );

            Log::info('[ProcessPulseDavFile] FileProcessingService returned', [
                'result' => $result,
            ]);

            // Mark as completed
            $this->pulseDavFile->update([
                'status' => 'completed',
                'processed_at' => now(),
                'file_id' => $result['fileId'] ?? null,
            ]);

            Log::info('[ProcessPulseDavFile] Job completed successfully', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'file_id' => $result['fileId'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('[ProcessPulseDavFile] Job failed', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->pulseDavFile->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        parent::failed($exception);
        $this->pulseDavFile->markAsFailed($exception->getMessage());
    }
}
