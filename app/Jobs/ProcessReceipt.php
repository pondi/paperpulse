<?php

namespace App\Jobs;

use App\Services\ConversionService;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\File;
use App\Models\Receipt;
use App\Notifications\ReceiptProcessed;

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
        try {
            $metadata = $this->getMetadata();
            if (!$metadata) {
                throw new \Exception('No metadata found for job');
            }

            Log::info("Processing receipt", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_guid' => $metadata['fileGUID']
            ]);

            $this->updateProgress(10);

            // Get the services
            $conversionService = app(ConversionService::class);
            $receiptService = app(ReceiptService::class);

            // Convert PDF to image for OCR
            if ($metadata['fileExtension'] === 'pdf') {
                $conversionService->pdfToImage(
                    $metadata['filePath'],
                    $metadata['fileGUID'],
                    app(\App\Services\DocumentService::class)
                );
            }

            $this->updateProgress(50);

            // Process the receipt data
            $receiptData = $receiptService->processReceiptData(
                $metadata['fileID'],
                $metadata['fileGUID'],
                $metadata['filePath']
            );

            // Cache the receipt data for MatchMerchant job
            Cache::put("job.{$this->jobID}.receiptMetaData", $receiptData, now()->addHours(1));

            // Verify cache data is set before proceeding
            $cachedData = Cache::get("job.{$this->jobID}.receiptMetaData");
            if (!$cachedData) {
                throw new \Exception('Failed to cache receipt data');
            }

            Log::debug("Receipt data cached for merchant matching", [
                'job_id' => $this->jobID,
                'receipt_id' => $receiptData['receiptID'],
                'cached_data' => $cachedData
            ]);

            $this->updateProgress(100);

            // Dispatch merchant matching job
            MatchMerchant::dispatch($this->jobID)
                ->onQueue('receipts')
                ->delay(now()->addSeconds(5));

            Log::info("Receipt processed successfully", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'receipt_id' => $receiptData['receiptID']
            ]);

            // Send notification to user
            try {
                $file = File::find($metadata['fileId']);
                if ($file && $file->user) {
                    $receipt = Receipt::find($receiptData['receiptID']);
                    if ($receipt) {
                        $file->user->notify(new ReceiptProcessed($receipt, true));
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to send receipt processed notification", [
                    'error' => $e->getMessage(),
                    'receipt_id' => $receiptData['receiptID']
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Receipt processing failed", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Send failure notification to user
            try {
                $metadata = $this->getMetadata();
                if ($metadata && isset($metadata['fileId'])) {
                    $file = File::find($metadata['fileId']);
                    if ($file && $file->user) {
                        // Create a temporary receipt object for the notification
                        $tempReceipt = new Receipt();
                        $tempReceipt->file_id = $file->id;
                        $tempReceipt->user_id = $file->user_id;
                        
                        $file->user->notify(new ReceiptProcessed($tempReceipt, false, $e->getMessage()));
                    }
                }
            } catch (\Exception $notifError) {
                Log::warning("Failed to send receipt failure notification", [
                    'error' => $notifError->getMessage()
                ]);
            }

            throw $e;
        }
    }
}
