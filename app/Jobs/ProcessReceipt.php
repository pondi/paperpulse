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

class ProcessReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobID;
    protected $fileID;
    protected $fileGUID;
    protected $filePath;
    protected $fileExtension;


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

        Log::info('ProcessReceipt Job before updateReceipt - FileID: ' . $this->fileID . ' fileGUID:' . $this->fileGUID . ' - Receipt parsed:', $parsedReceipt);

        $receiptMetaData = $this->createReceipt($parsedReceipt);
        Cache::put("job.{$this->jobID}.receiptMetaData", $receiptMetaData, now()->addMinutes(5));

        Log::info('ProcessReceipt Job - fileGUID:' . $this->fileGUID . ' - Receipt processed successfully');
    }

    private function fetchDataFromCache()
    {
        $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
        $this->fileID = $fileMetaData['fileID'];
        $this->fileGUID = $fileMetaData['fileGUID'];
        $this->filePath = $fileMetaData['filePath'];
        $this->fileExtension = $fileMetaData['fileExtension'];

        Log::info('ProcessReceipt Job - fileGUID:' . $this->fileGUID . ' - filePath: ' . $this->filePath . ' - jobID: ' . $this->jobID);
    }

    private function readFileContent()
    {
        $filePath = 'uploads/' . $this->fileGUID . '.' . $this->fileExtension;
        if (!Storage::disk('local')->exists($filePath)) {
            Log::error('File does not exist: ' . $filePath);
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

        Log::info('Creating receipt - ReceiptID:' . $this->fileID . ' - Receipt created:', $receipt->toArray());

        return [
            'receiptID' => $receipt->id,
            'merchantName' => $parsedReceipt['merchant']['name'],
            'merchantAddress' => $parsedReceipt['merchant']['address'],
            'merchantVatID' => $parsedReceipt['merchant']['vatId'],
        ];
    }
}
