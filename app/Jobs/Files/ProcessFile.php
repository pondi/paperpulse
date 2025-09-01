<?php

namespace App\Jobs\Files;

use App\Jobs\BaseJob;
use App\Services\ConversionService;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Log;

class ProcessFile extends BaseJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Process File';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            Log::info('[ProcessFile] Starting job execution', [
                'job_id' => $this->jobID,
                'uuid' => $this->uuid,
            ]);

            $metadata = $this->getMetadata();
            if (! $metadata) {
                throw new \Exception('No metadata found for job');
            }

            $fileType = $metadata['fileType'] ?? 'receipt';
            $jobName = $metadata['jobName'] ?? 'unknown';

            Log::info("[ProcessFile] [{$jobName}] Processing file", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_path' => $metadata['filePath'],
                'file_type' => $fileType,
                'file_guid' => $metadata['fileGuid'],
            ]);

            $this->updateProgress(10);

            // Get services
            $conversionService = app(ConversionService::class);
            $storageService = app(StorageService::class);
            $textExtractionService = app(TextExtractionService::class);

            // Handle file type specific processing
            if ($fileType === 'receipt') {
                // For receipts, convert to image if PDF (existing behavior)
                if ($metadata['fileExtension'] === 'pdf') {
                    Log::debug("[ProcessFile] [{$jobName}] Converting PDF to image for receipt processing");

                    // This will be handled by ProcessReceipt job
                    // Just ensure the file is ready
                }
                $this->updateProgress(50);
            } else {
                // For documents, we keep the original format
                // The file is already stored in S3 by FileProcessingService
                Log::debug("[ProcessFile] [{$jobName}] Document file ready for processing", [
                    's3_path' => $metadata['s3OriginalPath'] ?? 'not set',
                ]);

                // Pre-extract text and cache it for document processing
                try {
                    $text = $textExtractionService->extract(
                        $metadata['filePath'],
                        $fileType,
                        $metadata['fileGuid']
                    );

                    $metadata['extractedText'] = $text;
                    $metadata['textLength'] = strlen($text);
                    $this->storeMetadata($metadata);

                    Log::debug("[ProcessFile] [{$jobName}] Text pre-extracted for document", [
                        'text_length' => strlen($text),
                    ]);
                } catch (\Exception $e) {
                    Log::warning("[ProcessFile] [{$jobName}] Text pre-extraction failed, will retry in ProcessDocument", [
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->updateProgress(50);
            }

            // Validate S3 storage
            if (isset($metadata['s3OriginalPath'])) {
                $exists = $storageService->getFile($metadata['s3OriginalPath']) !== null;
                if (! $exists) {
                    throw new \Exception('File not found in S3 storage');
                }
                Log::debug("[ProcessFile] [{$jobName}] S3 storage validated", [
                    'path' => $metadata['s3OriginalPath'],
                ]);
            }

            $this->updateProgress(100);

            Log::info("[ProcessFile] [{$jobName}] File processed successfully", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_type' => $fileType,
            ]);
        } catch (\Exception $e) {
            Log::error('[ProcessFile] File processing failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
