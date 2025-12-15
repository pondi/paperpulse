<?php

namespace App\Jobs\PulseDav;

use App\Exceptions\DuplicateFileException;
use App\Jobs\BaseJob;
use App\Models\PulseDavFile;
use App\Services\FileProcessingService;
use App\Services\PulseDavService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessPulseDavFile extends BaseJob
{
    public $timeout = 3600;

    public $tries = 5;

    public $backoff = 10;

    protected $pulseDavFile;

    protected $tagIds;

    protected $note;

    /**
     * Create a new job instance.
     */
    public function __construct(PulseDavFile $pulseDavFile, array $tagIds = [], ?string $note = null)
    {
        // Generate a unique job ID for this processing chain
        $jobID = (string) Str::uuid();
        parent::__construct($jobID);

        $this->pulseDavFile = $pulseDavFile;
        $this->tagIds = $tagIds;
        $this->note = $note;
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
                'note' => $this->note,
                'filename' => $this->pulseDavFile->filename,
                'status' => $this->pulseDavFile->status,
            ]);

            // Mark as processing
            $this->pulseDavFile->markAsProcessing();

            Log::info('[ProcessPulseDavFile] Calling FileProcessingService', [
                'method' => 'processPulseDavFile',
                's3_path' => $this->pulseDavFile->s3_path,
                'user_id' => $this->pulseDavFile->user_id,
                'note' => $this->note,
            ]);

            // Use the unified FileProcessingService to process the file
            $result = $fileProcessingService->processPulseDavFile(
                $this->pulseDavFile->s3_path,
                $this->pulseDavFile->file_type ?? 'receipt',
                $this->pulseDavFile->user_id,
                [
                    'tagIds' => $this->tagIds,
                    'note' => $this->note,
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

        } catch (DuplicateFileException $e) {
            // Handle duplicate file - keep in PulseDav bucket, just mark as completed
            Log::info('[ProcessPulseDavFile] Duplicate file detected, skipping processing', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                's3_path' => $this->pulseDavFile->s3_path,
                'file_hash' => $e->getFileHash(),
                'existing_file_id' => $e->getExistingFile()->id,
            ]);

            // Mark as completed and link to existing file
            // Note: We keep the file in PulseDav bucket for now
            $this->pulseDavFile->update([
                'status' => 'completed',
                'processed_at' => now(),
                'file_id' => $e->getExistingFile()->id, // Link to the existing file
            ]);

            Log::info('[ProcessPulseDavFile] Duplicate file marked as completed, file kept in PulseDav', [
                'job_id' => $this->jobID,
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'existing_file_id' => $e->getExistingFile()->id,
                's3_path' => $this->pulseDavFile->s3_path,
            ]);

        } catch (Exception $e) {
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
    public function failed(Throwable $exception): void
    {
        parent::failed($exception);
        $this->pulseDavFile->markAsFailed($exception->getMessage());
    }
}
