<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Models\LineItem;
use HelgeSverre\ReceiptScanner\ReceiptScanner;
use HelgeSverre\ReceiptScanner\Facades\Text;
use HelgeSverre\ReceiptScanner\ModelNames;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Termwind\Components\Raw;
use Illuminate\Support\Facades\DB;

class ProcessReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobID;
    protected $fileID;
    protected $fileGUID;
    protected $filePath;
    protected $fileExtension;
    protected $jobName;

    public $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    public function __construct($jobID)
    {
        $this->jobID = $jobID;

    }

    public function handle()
    {

        $this->fetchDataFromCache();
        $fileContent = $this->readFileContent();

        $parsedReceipt = $this->parseReceipt($fileContent);

        Log::debug("(ProcessReceipt) [{$this->jobName}] - Receipt parsed (file: {$this->fileGUID})", [
            'file_id' => $this->fileID,
            'parsed_data' => $parsedReceipt
        ]);

        $receiptMetaData = $this->createReceipt($parsedReceipt);
        Cache::put("job.{$this->jobID}.receiptMetaData", $receiptMetaData, now()->addMinutes(5));

        Log::info("(ProcessReceipt) [{$this->jobName}] - Receipt processing completed (file: {$this->fileGUID})");
    }

    private function fetchDataFromCache()
    {
        $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
        $this->fileID = $fileMetaData['fileID'];
        $this->fileGUID = $fileMetaData['fileGUID'];
        $this->filePath = $fileMetaData['filePath'];
        $this->fileExtension = $fileMetaData['fileExtension'];
        $this->jobName = $fileMetaData['jobName'];

        Log::debug("(ProcessReceipt) [{$this->jobName}] - Starting file processing (file: {$this->fileGUID})", [
            'file_path' => $this->filePath,
            'job_id' => $this->jobID
        ]);
    }

    private function readFileContent()
    {
        $filePath = 'uploads/' . $this->fileGUID . '.' . $this->fileExtension;
        if (!Storage::disk('local')->exists($filePath)) {
            Log::error("(ProcessReceipt) [{$this->jobName}] - File not found (file: {$this->fileGUID})", [
                'file_path' => $filePath
            ]);
            return null;
        }

        return Storage::disk('local')->get($filePath);
    }

    private function parseReceipt($fileContent) : array
    {
        $scanner = new ReceiptScanner();
        $textPdfOcr = Text::textractUsingS3Upload($fileContent);
        $parsedReceipt = $scanner->scan(
            text: $textPdfOcr,
            model: ModelNames::TURBO_INSTRUCT,
            maxTokens: 500,
            temperature: 0.2,
            template: 'norwegian-receipt',
            asArray: true,
        );

        return $parsedReceipt;
    }

    private function createReceipt(array $parsedReceipt) : array
    {
        DB::beginTransaction();
        try {
            $receipt = new Receipt;
            $receipt->file_id = $this->fileID;
            $receipt->receipt_data = json_encode($parsedReceipt);
            $receipt->receipt_date = $parsedReceipt['date'] ?? null;
            $receipt->tax_amount = $parsedReceipt['taxAmount'] ?? null;
            $receipt->total_amount = $parsedReceipt['totalAmount'] ?? null;
            $receipt->currency = $parsedReceipt['currency'] ?? null;
            $receipt->receipt_category = $parsedReceipt['category'] ?? null;
            $receipt->receipt_description = $parsedReceipt['description'] ?? null;
            $receipt->save();

            foreach ($parsedReceipt['lineItems'] as $item) {
                $lineItem = new LineItem;
                $lineItem->receipt_id = $receipt->id;
                $lineItem->text = $item['text'];
                $lineItem->sku = $item['sku'];
                $lineItem->qty = $item['qty'];
                $lineItem->price = $item['price'];
                $lineItem->save();
            }

            DB::commit();

            // Make the receipt searchable after all relations are saved
            $receipt->load(['merchant', 'lineItems']);
            $receipt->searchable();

            Log::info("(ProcessReceipt) [{$this->jobName}] - Receipt created (file: {$this->fileGUID})");

            return [
                'receiptID' => $receipt->id,
                'merchantName' => $parsedReceipt['merchant']['name'],
                'merchantAddress' => $parsedReceipt['merchant']['address'],
                'merchantVatID' => $parsedReceipt['merchant']['vatId'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("(ProcessReceipt) [{$this->jobName}] - Receipt creation failed (file: {$this->fileGUID})", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
