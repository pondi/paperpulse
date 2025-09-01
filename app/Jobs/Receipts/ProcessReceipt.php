<?php

namespace App\Jobs\Receipts;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Models\Receipt;
use App\Notifications\ReceiptProcessed;
use App\Services\ConversionService;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                throw new \Exception('No metadata found for job');
            }

            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Metadata loaded', [
                    'job_id' => $this->jobID,
                    'metadata' => $metadata,
                    'file_exists' => file_exists($metadata['filePath'] ?? ''),
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

            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Services loaded', [
                    'job_id' => $this->jobID,
                    'conversion_service' => get_class($conversionService),
                    'receipt_service' => get_class($receiptService),
                ]);
            }

            // Convert PDF to image for OCR
            if ($metadata['fileExtension'] === 'pdf') {
                if ($debugEnabled) {
                    Log::debug('[ProcessReceipt] Converting PDF to image', [
                        'job_id' => $this->jobID,
                        'file_path' => $metadata['filePath'],
                        'file_guid' => $metadata['fileGuid'],
                    ]);
                }

                $conversionResult = $conversionService->pdfToImage(
                    $metadata['filePath'],
                    $metadata['fileGuid'],
                    app(\App\Services\DocumentService::class)
                );

                if ($conversionResult) {
                    Log::info('[ProcessReceipt] PDF conversion completed successfully', [
                        'job_id' => $this->jobID,
                        'file_guid' => $metadata['fileGuid'],
                    ]);
                } else {
                    Log::warning('[ProcessReceipt] PDF conversion failed, proceeding with direct PDF processing', [
                        'job_id' => $this->jobID,
                        'file_guid' => $metadata['fileGuid'],
                    ]);
                    // Continue processing - OCR service may still be able to handle the PDF directly
                }
            }

            $this->updateProgress(50);

            // Process the receipt data
            if ($debugEnabled) {
                Log::debug('[ProcessReceipt] Starting receipt data processing', [
                    'job_id' => $this->jobID,
                    'file_id' => $metadata['fileId'],
                    'file_guid' => $metadata['fileGuid'],
                    'file_path' => $metadata['filePath'],
                ]);
            }

            $receiptData = $receiptService->processReceiptData(
                $metadata['fileId'],
                $metadata['fileGuid'],
                $metadata['filePath']
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

            // Update file status to completed and generate preview image
            try {
                $file = File::find($metadata['fileId']);
                if ($file) {
                    // Generate thumbnail preview with proper error handling
                    $thumbnailData = null;
                    $thumbnailError = null;

                    try {
                        $thumbnailData = $this->generateThumbnail($metadata['filePath'], $metadata['fileGuid']);
                    } catch (\Exception $thumbError) {
                        $thumbnailError = $thumbError->getMessage();
                        Log::warning('[ProcessReceipt] Thumbnail generation failed', [
                            'job_id' => $this->jobID,
                            'file_guid' => $metadata['fileGuid'],
                            'error' => $thumbnailError,
                        ]);
                    }

                    $file->status = 'completed';
                    $file->s3_processed_path = $metadata['s3OriginalPath']; // Use original as processed for receipts

                    // Only set thumbnail if generation was successful
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
                }
            } catch (\Exception $e) {
                Log::error('[ProcessReceipt] Failed to update file status', [
                    'job_id' => $this->jobID,
                    'file_id' => $metadata['fileId'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't throw here - file processing succeeded, only status update failed
            }

            $this->updateProgress(100);

            // Merchant matching is now handled by the main job chain in FileProcessingService
            // No need to dispatch it separately - the chain will handle MatchMerchant after this job completes

            Log::info('Receipt processed successfully', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'receipt_id' => $receiptData['receiptId'],
            ]);

            // Send notification to user
            try {
                $file = File::find($metadata['fileId']);
                if ($file && $file->user) {
                    $receipt = Receipt::find($receiptData['receiptId']);
                    if ($receipt) {
                        $file->user->notify(new ReceiptProcessed($receipt, true));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to send receipt processed notification', [
                    'error' => $e->getMessage(),
                    'receipt_id' => $receiptData['receiptId'],
                ]);
            }
        } catch (\Exception $e) {
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
            } catch (\Exception $notifError) {
                Log::warning('Failed to send receipt failure notification', [
                    'error' => $notifError->getMessage(),
                ]);
            }

            throw $e;
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
            $imagick = new \Imagick($filePath);

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
        } catch (\ImagickException $e) {
            Log::error('[ProcessReceipt] Imagick error during thumbnail generation', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'error_type' => 'ImagickException',
            ]);
            throw new \Exception("Thumbnail generation failed: {$e->getMessage()}");
        } catch (\Exception $e) {
            Log::error('[ProcessReceipt] Unexpected error during thumbnail generation', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw new \Exception("Thumbnail generation failed: {$e->getMessage()}");
        }
    }
}
