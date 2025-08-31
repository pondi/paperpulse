<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\Receipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MatchMerchant extends BaseJob
{
    protected $fileId;

    protected $receiptId;

    protected $merchantName;

    protected $merchantAddress;

    protected $merchantVatNumber;

    public $timeout = 3600;

    public $tries = 5;

    public $backoff = 10;

    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Match Merchant';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            $this->fetchDataFromCache();
            $this->updateProgress(25);

            $merchants = $this->fetchAllMerchants();
            $this->updateProgress(50);

            $bestMatch = $this->getBestMatchFromAI($merchants);
            $this->updateProgress(75);

            if (! $bestMatch || ! isset($bestMatch['name'])) {
                $this->createMerchant();
                $this->updateProgress(100);

                return;
            }

            $matchedMerchantName = $bestMatch['name'];
            $merchantIds = array_column($merchants, 'id', 'name');

            if (! isset($merchantIds[$matchedMerchantName])) {
                $this->createMerchant();
                $this->updateProgress(100);

                return;
            }

            $matchedMerchantId = $merchantIds[$matchedMerchantName];
            $this->updateReceipt($matchedMerchantId);
            $this->updateMerchant($matchedMerchantId);
            $this->updateProgress(100);

        } catch (\Exception $e) {
            Log::error('Merchant matching failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        $requiredFileFields = ['fileId', 'jobName'];
        $requiredReceiptFields = ['receiptId', 'merchantName'];

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
        $this->fileId = $fileMetaData['fileId'];
        $this->jobName = $fileMetaData['jobName'];
        $this->receiptId = $receiptMetaData['receiptId'];
        $this->merchantName = $receiptMetaData['merchantName'];
        $this->merchantAddress = $receiptMetaData['merchantAddress'] ?? null;
        $this->merchantVatNumber = $receiptMetaData['merchantVatID'] ?? null;

        Log::debug("(MatchMerchant) [{$this->jobName}] - Cache data fetched successfully", [
            'jobID' => $this->jobID,
            'fileId' => $this->fileId,
            'receiptId' => $this->receiptId,
            'merchantName' => $this->merchantName,
        ]);
    }

    private function fetchAllMerchants()
    {
        return Merchant::all()->map(function ($merchant) {
            return ['id' => $merchant->id, 'name' => $merchant->name];
        })->toArray();
    }

    private function getBestMatchFromAI($merchants)
    {
        try {
            // Use simple string matching for merchant matching since AI service doesn't have a completion method
            $bestMatch = $this->findBestMerchantMatch($merchants, $this->merchantName);

            Log::debug("(MatchMerchant) [{$this->jobName}] - Merchant match found (receipt: {$this->receiptId})", [
                'merchant_name' => $this->merchantName,
                'match' => $bestMatch,
            ]);

            return $bestMatch;

        } catch (\Exception $e) {
            Log::error("(MatchMerchant) [{$this->jobName}] - Merchant matching failed", [
                'merchant_name' => $this->merchantName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find best merchant match using string similarity
     */
    private function findBestMerchantMatch($merchants, $targetName)
    {
        if (empty($merchants) || empty($targetName)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;
        $threshold = 0.8; // 80% similarity required

        foreach ($merchants as $merchant) {
            // Calculate similarity using levenshtein distance
            $name = strtolower(trim($merchant['name']));
            $target = strtolower(trim($targetName));

            // Exact match
            if ($name === $target) {
                return $merchant;
            }

            // Substring match
            if (str_contains($name, $target) || str_contains($target, $name)) {
                $score = max(strlen($target) / strlen($name), strlen($name) / strlen($target));
                if ($score > $bestScore && $score >= $threshold) {
                    $bestScore = $score;
                    $bestMatch = $merchant;
                }
            }

            // Levenshtein similarity
            $maxLen = max(strlen($name), strlen($target));
            if ($maxLen > 0) {
                $distance = levenshtein($name, $target);
                $score = 1 - ($distance / $maxLen);

                if ($score > $bestScore && $score >= $threshold) {
                    $bestScore = $score;
                    $bestMatch = $merchant;
                }
            }
        }

        return $bestMatch;
    }

    private function updateReceipt($merchantId)
    {
        $receipt = Receipt::find($this->receiptId);
        if ($receipt) {
            $receipt->merchant_id = $merchantId;
            $receipt->save();
            Log::info("(MatchMerchant) [{$this->jobName}] - Receipt updated (receipt: {$this->receiptId})");
        } else {
            Log::error("(MatchMerchant) [{$this->jobName}] - Receipt not found (receipt: {$this->receiptId})");
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
            Log::info("(MatchMerchant) [{$this->jobName}] - Merchant updated (receipt: {$this->receiptId})");
        } else {
            Log::error("(MatchMerchant) [{$this->jobName}] - Merchant not found (receipt: {$this->receiptId}, merchant: {$merchantId})");
        }
    }

    private function createMerchant()
    {
        $newMerchant = Merchant::create(['name' => $this->merchantName, 'address' => $this->merchantAddress, 'vat_number' => $this->merchantVatNumber]);

        $this->updateReceipt($newMerchant->id);

        Log::info("(MatchMerchant) [{$this->jobName}] - New merchant created (receipt: {$this->receiptId})");
    }
}
