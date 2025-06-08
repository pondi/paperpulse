<?php

namespace App\Jobs;

use App\Models\PulseDavFile;
use App\Services\FileProcessingService;
use App\Services\PulseDavService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPulseDavFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $this->pulseDavFile = $pulseDavFile;
        $this->tagIds = $tagIds;
    }

    /**
     * Execute the job.
     */
    public function handle(PulseDavService $pulseDavService, FileProcessingService $fileProcessingService)
    {
        try {
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
                ]
            );

            // Mark as completed
            $this->pulseDavFile->update([
                'status' => 'completed',
                'processed_at' => now(),
                'file_id' => $result['fileId'] ?? null,
            ]);

            Log::info('PulseDav file processed successfully', [
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'file_id' => $result['fileId'] ?? null,
            ]);

        } catch (\Exception $e) {
            $this->pulseDavFile->markAsFailed($e->getMessage());

            Log::error('Failed to process PulseDav file', [
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
