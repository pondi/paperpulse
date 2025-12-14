<?php

namespace App\Jobs\Receipts;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Models\Receipt;
use App\Notifications\ReceiptProcessed;
use App\Services\ConversionService;
use App\Services\Files\FilePreviewManager;
use App\Services\ReceiptService;
use App\Services\Workers\WorkerFileManager;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Converts receipt files when needed and performs receipt extraction flow.
 *
 * Steps:
 * - Optional PDF->image conversion
 * - OCR + analysis via ReceiptService
 * - Update File status and generate thumbnail when possible
 * - Notify user on success/failure
 */
class ProcessReceipt extends BaseJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Process Receipt';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        $debugEnabled = config('app.debug');
        $startTime = microtime(true);
        $localFilePath = null;

        if ($debugEnabled) {
            Log::debug('[ProcessReceipt] Job starting', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'job_name' => $this->jobName,
                'timestamp' => now()->toISOString(),
            ]);
        }

        try {
            $metadata = $this->getMetadata();
            if (! $metadata) {
                throw new Exception('No metadata found for job');
            }
            $note = $metadata['metadata']['note'] ?? null;

            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Metadata loaded', [
                    'job_id' => $this->jobID,
                    'metadata' => $metadata,
                    's3_path' => $metadata['s3OriginalPath'] ?? null,
                ]);
            }

            Log::info('[ProcessReceipt] Processing receipt', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_guid' => $metadata['fileGuid'],
                'file_extension' => $metadata['fileExtension'] ?? 'unknown',
                'user_id' => $metadata['userId'] ?? 'unknown',
            ]);

            $this->updateProgress(10);

            // Get the services
            $conversionService = app(ConversionService::class);
            $receiptService = app(ReceiptService::class);
            $workerFileManager = app(WorkerFileManager::class);

            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Services loaded', [
                    'job_id' => $this->jobID,
                    'conversion_service' => get_class($conversionService),
                    'receipt_service' => get_class($receiptService),
                    'worker_file_manager' => get_class($workerFileManager),
                ]);
            }

            $this->runReceiptPipeline(
                $metadata,
                $note,
                $debugEnabled,
                $startTime,
                $localFilePath,
                $receiptService,
                $workerFileManager
            );
        } catch (Exception $e) {
            Log::error('Receipt processing failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification to user
            try {
                $metadata = $this->getMetadata();
                if ($metadata && isset($metadata['fileId'])) {
                    $file = File::find($metadata['fileId']);
                    if ($file && $file->user) {
                        // Create a temporary receipt object for the notification
                        $tempReceipt = new Receipt;
                        $tempReceipt->file_id = $file->id;
                        $tempReceipt->user_id = $file->user_id;

                        $file->user->notify(new ReceiptProcessed($tempReceipt, false, $e->getMessage()));
                    }
                }
            } catch (Exception $notifError) {
                Log::warning('Failed to send receipt failure notification', [
                    'error' => $notifError->getMessage(),
                ]);
            }

            throw $e;
        } finally {
            // Always clean up local file, even if processing failed
            if ($localFilePath) {
                $workerFileManager = app(WorkerFileManager::class);
                $workerFileManager->cleanupLocalFile(
                    $localFilePath,
                    $metadata['fileGuid'] ?? 'unknown',
                    'ProcessReceipt'
                );
            }
        }
    }

    /**
     * Generate thumbnail preview of the receipt image
     */
    private function generateThumbnail(string $filePath, string $fileGuid): ?string
    {
        // Check if file exists
        if (! file_exists($filePath)) {
            Log::warning('[ProcessReceipt] File not found for thumbnail generation', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
            ]);

            return null;
        }

        // Check if imagick extension is available
        if (! extension_loaded('imagick')) {
            Log::warning('[ProcessReceipt] Imagick extension not available, cannot generate thumbnail', [
                'file_guid' => $fileGuid,
            ]);

            return null;
        }

        try {
            // Create thumbnail using Imagick
            $imagick = new Imagick($filePath);

            // Get original dimensions for logging
            $originalWidth = $imagick->getImageWidth();
            $originalHeight = $imagick->getImageHeight();

            // Resize to thumbnail size (max 300px width/height, maintain aspect ratio)
            $imagick->thumbnailImage(300, 300, true, true);

            // Convert to JPEG with 85% quality
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);

            // Get image data as base64 string
            $thumbnailData = base64_encode($imagick->getImageBlob());

            // Get thumbnail dimensions
            $thumbWidth = $imagick->getImageWidth();
            $thumbHeight = $imagick->getImageHeight();

            $imagick->clear();
            $imagick->destroy();

            Log::info('[ProcessReceipt] Thumbnail generated successfully', [
                'file_guid' => $fileGuid,
                'original_dimensions' => "{$originalWidth}x{$originalHeight}",
                'thumbnail_dimensions' => "{$thumbWidth}x{$thumbHeight}",
                'thumbnail_size_bytes' => strlen($thumbnailData),
            ]);

            return $thumbnailData;
        } catch (ImagickException $e) {
            Log::error('[ProcessReceipt] Imagick error during thumbnail generation', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'error_type' => 'ImagickException',
            ]);
            throw new Exception("Thumbnail generation failed: {$e->getMessage()}");
        } catch (Exception $e) {
            Log::error('[ProcessReceipt] Unexpected error during thumbnail generation', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw new Exception("Thumbnail generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Core processing pipeline for receipt jobs.
     */
    private function runReceiptPipeline(
        array $metadata,
        ?string $note,
        bool $debugEnabled,
        float $startTime,
        ?string &$localFilePath,
        ReceiptService $receiptService,
        WorkerFileManager $workerFileManager
    ): void {
        // Ensure file is available locally (download from S3 if needed)
        $localFilePath = $workerFileManager->ensureLocalFile(
            $metadata['s3OriginalPath'],
            $metadata['fileGuid'],
            $metadata['fileExtension'],
            $metadata['filePath'] ?? null
        );

        Log::debug('[ProcessReceipt] File available for processing', [
            'job_id' => $this->jobID,
            'local_path' => $localFilePath,
            'file_guid' => $metadata['fileGuid'],
        ]);

        $this->updateProgress(20);

        // Generate image preview for PDF files
        $file = File::find($metadata['fileId']);
        if ($file && $metadata['fileExtension'] === 'pdf') {
            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Generating image preview for PDF', [
                    'job_id' => $this->jobID,
                    'file_path' => $localFilePath,
                    'file_guid' => $metadata['fileGuid'],
                ]);
            }

            try {
                $previewManager = app(FilePreviewManager::class);
                $previewGenerated = $previewManager->generatePreviewForFile($file, $localFilePath);

                if ($previewGenerated) {
                    Log::info('[ProcessReceipt] Image preview generated successfully', [
                        'job_id' => $this->jobID,
                        'file_guid' => $metadata['fileGuid'],
                        'preview_path' => $file->s3_image_path,
                    ]);
                } else {
                    Log::warning('[ProcessReceipt] Image preview generation skipped or failed', [
                        'job_id' => $this->jobID,
                        'file_guid' => $metadata['fileGuid'],
                    ]);
                }
            } catch (Exception $e) {
                Log::error('[ProcessReceipt] Exception during preview generation', [
                    'job_id' => $this->jobID,
                    'file_guid' => $metadata['fileGuid'],
                    'error' => $e->getMessage(),
                ]);
                // Continue processing - OCR will still work with the PDF
            }
        }

        $this->updateProgress(50);

        // Process the receipt data
        if ($debugEnabled) {
            Log::debug('[ProcessReceipt] Starting receipt data processing', [
                'job_id' => $this->jobID,
                'file_id' => $metadata['fileId'],
                'file_guid' => $metadata['fileGuid'],
                'file_path' => $localFilePath,
            ]);
        }

        $receiptData = $receiptService->processReceiptData(
            $metadata['fileId'],
            $metadata['fileGuid'],
            $localFilePath,
            $note
        );

        if ($debugEnabled) {
            Log::debug('[ProcessReceipt] Receipt data processed', [
                'job_id' => $this->jobID,
                'receipt_data' => $receiptData,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        }

        // Cache the receipt data for potential job restarts (increased TTL)
        Cache::put("job.{$this->jobID}.receiptMetaData", $receiptData, now()->addHours(4));

        Log::debug('Receipt data cached for merchant matching', [
            'job_id' => $this->jobID,
            'receipt_id' => $receiptData['receiptId'],
            'merchant_name' => $receiptData['merchantName'],
        ]);

        $this->updateFileStatusAndThumbnail($metadata, $localFilePath, $receiptData);

        $this->updateProgress(100);

        // Merchant matching is now handled by the main job chain in FileProcessingService
        // No need to dispatch it separately - the chain will handle MatchMerchant after this job completes

        Log::info('Receipt processed successfully', [
            'job_id' => $this->jobID,
            'task_id' => $this->uuid,
            'receipt_id' => $receiptData['receiptId'],
        ]);

        $this->notifyUserOnSuccess($metadata, $receiptData);
    }

    private function updateFileStatusAndThumbnail(array $metadata, string $localFilePath, array $receiptData): void
    {
        try {
            $file = File::find($metadata['fileId']);
            if (! $file) {
                return;
            }

            $thumbnailData = null;
            $thumbnailError = null;

            try {
                $thumbnailData = $this->generateThumbnail($localFilePath, $metadata['fileGuid']);
            } catch (Exception $thumbError) {
                $thumbnailError = $thumbError->getMessage();
                Log::warning('[ProcessReceipt] Thumbnail generation failed', [
                    'job_id' => $this->jobID,
                    'file_guid' => $metadata['fileGuid'],
                    'error' => $thumbnailError,
                ]);
            }

            $file->status = 'completed';
            $file->s3_processed_path = $metadata['s3OriginalPath']; // Use original as processed for receipts

            if ($thumbnailData) {
                $file->fileImage = $thumbnailData;
            }

            $file->save();

            Log::info('[ProcessReceipt] File status updated to completed', [
                'job_id' => $this->jobID,
                'file_id' => $file->id,
                'file_guid' => $file->guid,
                's3_processed_path' => $file->s3_processed_path,
                'has_thumbnail' => ! empty($thumbnailData),
                'thumbnail_error' => $thumbnailError,
            ]);
        } catch (Exception $e) {
            Log::error('[ProcessReceipt] Failed to update file status', [
                'job_id' => $this->jobID,
                'file_id' => $metadata['fileId'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function notifyUserOnSuccess(array $metadata, array $receiptData): void
    {
        try {
            $file = File::find($metadata['fileId']);
            if ($file && $file->user) {
                $receipt = Receipt::find($receiptData['receiptId']);
                if ($receipt) {
                    $file->user->notify(new ReceiptProcessed($receipt, true));
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to send receipt processed notification', [
                'error' => $e->getMessage(),
                'receipt_id' => $receiptData['receiptId'] ?? null,
            ]);
        }
    }
}
