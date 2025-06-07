<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class MatchMerchant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobID;

    protected $fileID;

    protected $receiptID;

    protected $merchantName;

    protected $merchantAddress;

    protected $merchantVatNumber;

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
        $merchants = $this->fetchAllMerchants();
        $bestMatch = $this->getBestMatchFromOpenAI($merchants);

        if (! $bestMatch || ! isset($bestMatch['name'])) {
            $this->createMerchant();

            return;
        }

        $matchedMerchantName = $bestMatch['name'];
        $merchantIds = array_column($merchants, 'id', 'name');

        if (! isset($merchantIds[$matchedMerchantName])) {
            $this->createMerchant();

            return;
        }

        $matchedMerchantId = $merchantIds[$matchedMerchantName];
        $this->updateReceipt($matchedMerchantId);
        $this->updateMerchant($matchedMerchantId);

    }

    private function fetchDataFromCache()
    {
        $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
        $receiptMetaData = Cache::get("job.{$this->jobID}.receiptMetaData");

        if (! $fileMetaData || ! $receiptMetaData) {
            Log::error('(MatchMerchant) - Required cache data missing', [
                'jobID' => $this->jobID,
                'fileMetaData' => $fileMetaData,
                'receiptMetaData' => $receiptMetaData,
            ]);
            throw new \RuntimeException("Required cache data missing for job {$this->jobID}");
        }

        // Validate required fields are present
        $requiredFileFields = ['fileID', 'jobName'];
        $requiredReceiptFields = ['receiptID', 'merchantName'];

        foreach ($requiredFileFields as $field) {
            if (! isset($fileMetaData[$field])) {
                Log::error('(MatchMerchant) - Required file metadata field missing', [
                    'jobID' => $this->jobID,
                    'missing_field' => $field,
                    'fileMetaData' => $fileMetaData,
                ]);
                throw new \RuntimeException("Required file metadata field {$field} missing for job {$this->jobID}");
            }
        }

        foreach ($requiredReceiptFields as $field) {
            if (! isset($receiptMetaData[$field])) {
                Log::error('(MatchMerchant) - Required receipt metadata field missing', [
                    'jobID' => $this->jobID,
                    'missing_field' => $field,
                    'receiptMetaData' => $receiptMetaData,
                ]);
                throw new \RuntimeException("Required receipt metadata field {$field} missing for job {$this->jobID}");
            }
        }

        // Assign values with null coalescing for optional fields
        $this->fileID = $fileMetaData['fileID'];
        $this->jobName = $fileMetaData['jobName'];
        $this->receiptID = $receiptMetaData['receiptID'];
        $this->merchantName = $receiptMetaData['merchantName'];
        $this->merchantAddress = $receiptMetaData['merchantAddress'] ?? null;
        $this->merchantVatNumber = $receiptMetaData['merchantVatID'] ?? null;

        Log::debug("(MatchMerchant) [{$this->jobName}] - Cache data fetched successfully", [
            'jobID' => $this->jobID,
            'fileID' => $this->fileID,
            'receiptID' => $this->receiptID,
            'merchantName' => $this->merchantName,
        ]);
    }

    private function fetchAllMerchants()
    {
        return Merchant::all()->map(function ($merchant) {
            return ['id' => $merchant->id, 'name' => $merchant->name];
        })->toArray();
    }

    private function getBestMatchFromOpenAI($merchants)
    {
        $response = OpenAI::completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => 'We have a list of merchant names: '.json_encode($merchants).'. Find the closest match to the input, but if you do not finde a close match then return an emtpy string. It needs to be really close. Here is the merchant you need to find in the list: '.$this->merchantName.
                        'You have to respond with a JSON object with the merchant name and id. If the merchant name is not found, respond with an empty string. Reply ONLY with JSON object or empty string and nothing else.',
            'max_tokens' => 500,
        ]);

        Log::debug("(MatchMerchant) [{$this->jobName}] - OpenAI response received (receipt: {$this->receiptID})", [
            'response' => $response->toArray(),
        ]);

        $text = trim($response['choices'][0]['text']);

        // If response is empty or just whitespace, return null
        if (empty($text)) {
            return null;
        }

        // Try to decode JSON response
        $decoded = json_decode(stripslashes($text), true);

        // If JSON decode failed or result is empty, return null
        if ($decoded === null || empty($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function updateReceipt($merchantId)
    {
        $receipt = Receipt::find($this->receiptID);
        if ($receipt) {
            $receipt->merchant_id = $merchantId;
            $receipt->save();
            Log::info("(MatchMerchant) [{$this->jobName}] - Receipt updated (receipt: {$this->receiptID})");
        } else {
            Log::error("(MatchMerchant) [{$this->jobName}] - Receipt not found (receipt: {$this->receiptID})");
        }
    }

    private function updateMerchant($merchantId)
    {
        $merchant = Merchant::find($merchantId);
        if ($merchant) {
            if ($this->merchantAddress) {
                $merchant->address = $this->merchantAddress;
            }
            if ($this->merchantVatNumber) {
                $merchant->vat_number = $this->merchantVatNumber;
            }
            $merchant->save();
            Log::info("(MatchMerchant) [{$this->jobName}] - Merchant updated (receipt: {$this->receiptID})");
        } else {
            Log::error("(MatchMerchant) [{$this->jobName}] - Merchant not found (receipt: {$this->receiptID}, merchant: {$merchantId})");
        }
    }

    private function createMerchant()
    {
        $newMerchant = Merchant::create(['name' => $this->merchantName, 'address' => $this->merchantAddress, 'vat_number' => $this->merchantVatNumber]);

        $this->updateReceipt($newMerchant->id);

        Log::info("(MatchMerchant) [{$this->jobName}] - New merchant created (receipt: {$this->receiptID})");
    }
}
