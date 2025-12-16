<?php

namespace App\Jobs\Files;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Services\ConversionService;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use App\Services\Workers\WorkerFileManager;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Prepares uploaded files for downstream processing.
 *
 * Responsibilities:
 * - Validate presence of metadata and S3 objects
 * - For documents, pre-extract and cache text
 * - For receipts, ensure conversion readiness
 */
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
                throw new Exception('No metadata found for job');
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

            if (isset($metadata['fileId'])) {
                $file = File::find($metadata['fileId']);
                if ($file && $file->status === 'pending') {
                    $file->status = 'processing';
                    $file->save();
                }
            }

            $this->updateProgress(10);

            // Get services
            $conversionService = app(ConversionService::class);
            $storageService = app(StorageService::class);
            $textExtractionService = app(TextExtractionService::class);

            // Check if file requires conversion to PDF
            $docConversionService = app(\App\Services\Documents\ConversionService::class);

            if ($docConversionService->requiresConversion($metadata['fileExtension'])) {
                Log::info("[ProcessFile] [{$jobName}] Office file detected, initiating conversion", [
                    'extension' => $metadata['fileExtension'],
                    'file_guid' => $metadata['fileGuid'],
                ]);

                try {
                    // Build output S3 path using StoragePathBuilder pattern (variant=archive)
                    $outputS3Path = StoragePathBuilder::storagePath(
                        $metadata['userId'],
                        $metadata['fileGuid'],
                        $fileType,
                        'archive', // variant
                        'pdf'      // extension
                    );

                    $file = File::find($metadata['fileId']);

                    // Queue conversion job to Redis
                    $conversion = $docConversionService->queueConversion(
                        $file,
                        $metadata['s3OriginalPath'],
                        $outputS3Path
                    );

                    $this->updateProgress(30);

                    // Wait for conversion (blocks for up to 120s, polling status at configured interval)
                    $result = $docConversionService->waitForCompletion(
                        $conversion,
                        config('processing.conversion.timeout', 120)
                    );

                    if ($result['success']) {
                        // Conversion succeeded - update file record and metadata
                        $file->s3_archive_path = $result['output_path'];
                        $file->save();

                        // Use archive PDF for downstream processing
                        $metadata['s3ArchivePath'] = $result['output_path'];
                        $metadata['originalExtension'] = $metadata['fileExtension'];
                        $metadata['fileExtension'] = 'pdf'; // TextExtraction uses PDF
                        $this->storeMetadata($metadata);

                        Log::info("[ProcessFile] [{$jobName}] Conversion completed", [
                            'conversion_id' => $conversion->id,
                            'output_path' => $result['output_path'],
                        ]);
                    } else {
                        // Conversion failed - log and continue with original
                        Log::warning("[ProcessFile] [{$jobName}] Conversion failed, using original file", [
                            'conversion_id' => $conversion->id,
                            'error' => $result['error'],
                        ]);

                        $metadata['conversionFailed'] = true;
                        $metadata['conversionError'] = $result['error'];
                        $this->storeMetadata($metadata);
                    }
                } catch (Exception $e) {
                    Log::error("[ProcessFile] [{$jobName}] Conversion process exception", [
                        'error' => $e->getMessage(),
                    ]);

                    // Don't fail entire job - continue with original
                    $metadata['conversionFailed'] = true;
                    $metadata['conversionError'] = $e->getMessage();
                    $this->storeMetadata($metadata);
                }
            }

            $this->updateProgress(35);

            $this->updateProgress(50);

            // Handle file type specific processing
            if ($fileType === 'receipt') {
                // For receipts, convert to image if PDF (existing behavior)
                if ($metadata['fileExtension'] === 'pdf') {
                    Log::debug("[ProcessFile] [{$jobName}] Converting PDF to image for receipt processing");

                    // This will be handled by ProcessReceipt job
                    // Just ensure the file is ready
                }
            } else {
                // For documents, we keep the original format
                // The file is already stored in S3 by FileProcessingService
                Log::debug("[ProcessFile] [{$jobName}] Document file ready for processing", [
                    's3_path' => $metadata['s3OriginalPath'] ?? 'not set',
                ]);

                // Pre-extract text and cache it for document processing
                $localFilePath = null;
                try {
                    // Download file from S3 to local temp for text extraction
                    // Use archive PDF if available, otherwise use original file
                    $s3PathToUse = $metadata['s3ArchivePath'] ?? $metadata['s3OriginalPath'];
                    $extensionToUse = isset($metadata['s3ArchivePath']) ? 'pdf' : $metadata['fileExtension'];

                    $workerFileManager = app(WorkerFileManager::class);
                    $localFilePath = $workerFileManager->ensureLocalFile(
                        $s3PathToUse,
                        $metadata['fileGuid'],
                        $extensionToUse
                    );

                    Log::debug("[ProcessFile] [{$jobName}] File downloaded for text extraction", [
                        'local_path' => $localFilePath,
                        'using_archive' => isset($metadata['s3ArchivePath']),
                        's3_path' => $s3PathToUse,
                    ]);

                    $text = $textExtractionService->extract(
                        $localFilePath,
                        $fileType,
                        $metadata['fileGuid']
                    );

                    $metadata['extractedText'] = $text;
                    $metadata['textLength'] = strlen($text);
                    $this->storeMetadata($metadata);

                    Log::debug("[ProcessFile] [{$jobName}] Text pre-extracted for document", [
                        'text_length' => strlen($text),
                    ]);
                } catch (Exception $e) {
                    Log::warning("[ProcessFile] [{$jobName}] Text pre-extraction failed, will retry in ProcessDocument", [
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    // Clean up local file
                    if ($localFilePath) {
                        $workerFileManager = app(WorkerFileManager::class);
                        $workerFileManager->cleanupLocalFile(
                            $localFilePath,
                            $metadata['fileGuid'],
                            'ProcessFile'
                        );
                    }
                }
            }

            // Validate S3 storage
            if (isset($metadata['s3OriginalPath'])) {
                $exists = $storageService->getFile($metadata['s3OriginalPath']) !== null;
                if (! $exists) {
                    throw new Exception('File not found in S3 storage');
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
        } catch (Exception $e) {
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
