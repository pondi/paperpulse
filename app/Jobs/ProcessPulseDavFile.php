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
            Log::info('ProcessPulseDavFile job started', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                's3_path' => $this->pulseDavFile->s3_path,
                'file_type' => $this->pulseDavFile->file_type,
                'tag_ids' => $this->tagIds,
            ]);
            
            // Mark as processing
            $this->pulseDavFile->markAsProcessing();

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

            // Mark as completed
            $this->pulseDavFile->update([
                'status' => 'completed',
                'processed_at' => now(),
                'file_id' => $result['fileId'] ?? null,
            ]);

            Log::info('PulseDav file processed successfully', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'file_id' => $result['fileId'] ?? null,
            ]);

        } catch (\Exception $e) {
            $this->pulseDavFile->markAsFailed($e->getMessage());

            Log::error('Failed to process PulseDav file', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        $this->pulseDavFile->markAsFailed($exception->getMessage());
    }
}
