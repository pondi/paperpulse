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

                $conversionService->pdfToImage(
                    $metadata['filePath'],
                    $metadata['fileGuid'],
                    app(\App\Services\DocumentService::class)
                );

                if ($debugEnabled) {
                    Log::debug('[ProcessReceipt] PDF conversion completed', [
                        'job_id' => $this->jobID,
                        'file_guid' => $metadata['fileGuid'],
                    ]);
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
                    // Generate thumbnail preview
                    $thumbnailData = $this->generateThumbnail($metadata['filePath'], $metadata['fileGuid']);

                    $file->status = 'completed';
                    $file->s3_processed_path = $metadata['s3OriginalPath']; // Use original as processed for receipts
                    $file->fileImage = $thumbnailData; // Store thumbnail data
                    $file->save();

                    Log::debug('[ProcessReceipt] File status updated to completed', [
                        'job_id' => $this->jobID,
                        'file_id' => $file->id,
                        'file_guid' => $file->guid,
                        's3_processed_path' => $file->s3_processed_path,
                        'has_thumbnail' => ! empty($thumbnailData),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to update file status', [
                    'job_id' => $this->jobID,
                    'file_id' => $metadata['fileId'],
                    'error' => $e->getMessage(),
                ]);
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
        try {
            // Check if imagick extension is available
            if (! extension_loaded('imagick')) {
                Log::debug('[ProcessReceipt] Imagick not available, skipping thumbnail generation', [
                    'file_guid' => $fileGuid,
                ]);

                return null;
            }

            // Create thumbnail using Imagick
            $imagick = new \Imagick($filePath);

            // Resize to thumbnail size (max 300px width/height, maintain aspect ratio)
            $imagick->thumbnailImage(300, 300, true, true);

            // Convert to JPEG with 85% quality
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);

            // Get image data as base64 string
            $thumbnailData = base64_encode($imagick->getImageBlob());

            $imagick->clear();
            $imagick->destroy();

            Log::debug('[ProcessReceipt] Thumbnail generated successfully', [
                'file_guid' => $fileGuid,
                'thumbnail_size' => strlen($thumbnailData),
            ]);

            return $thumbnailData;
        } catch (\Exception $e) {
            Log::warning('[ProcessReceipt] Failed to generate thumbnail', [
                'file_guid' => $fileGuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
