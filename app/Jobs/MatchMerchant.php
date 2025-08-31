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

    protected $useDirectData = false;

    public $timeout = 3600;

    public $tries = 5;

    public $backoff = 10;

    public function __construct(
        string $jobID,
        ?int $receiptId = null,
        ?string $merchantName = null,
        ?string $merchantAddress = null,
        ?string $merchantVatNumber = null
    ) {
        parent::__construct($jobID);
        $this->jobName = 'Match Merchant';

        // If data is provided directly, use it instead of cache
        if ($receiptId !== null) {
            $this->useDirectData = true;
            $this->receiptId = $receiptId;
            $this->merchantName = $merchantName ?? '';
            $this->merchantAddress = $merchantAddress;
            $this->merchantVatNumber = $merchantVatNumber;

            Log::debug('(MatchMerchant) Direct data provided', [
                'jobID' => $this->jobID,
                'receiptId' => $this->receiptId,
                'merchantName' => $this->merchantName,
                'useDirectData' => $this->useDirectData,
            ]);
        }
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            // Use direct data if available, otherwise fall back to cache
            if (! $this->useDirectData) {
                $this->fetchDataFromCache();
            } else {
                // Get fileId from cache for direct data usage
                $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
                if ($fileMetaData && isset($fileMetaData['fileId'])) {
                    $this->fileId = $fileMetaData['fileId'];
                } else {
                    Log::warning('(MatchMerchant) Could not get fileId from cache, continuing without it', [
                        'jobID' => $this->jobID,
                    ]);
                }
            }

            $this->updateProgress(25);

            // Handle empty merchant name gracefully
            if (empty($this->merchantName) || trim($this->merchantName) === '') {
                Log::info("(MatchMerchant) [{$this->jobName}] - Empty merchant name, skipping merchant matching", [
                    'job_id' => $this->jobID,
                    'receipt_id' => $this->receiptId,
                ]);
                $this->updateProgress(100);

                return;
            }

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
                'use_direct_data' => $this->useDirectData,
                'merchant_name' => $this->merchantName ?? 'null',
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
                'fileMetaData_exists' => $fileMetaData !== null,
                'receiptMetaData_exists' => $receiptMetaData !== null,
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
                ]);
                throw new \RuntimeException("Required file metadata field {$field} missing for job {$this->jobID}");
            }
        }

        foreach ($requiredReceiptFields as $field) {
            if (! isset($receiptMetaData[$field])) {
                Log::error('(MatchMerchant) - Required receipt metadata field missing', [
                    'jobID' => $this->jobID,
                    'missing_field' => $field,
                ]);
                throw new \RuntimeException("Required receipt metadata field {$field} missing for job {$this->jobID}");
            }
        }

        // Assign values
        $this->fileId = $fileMetaData['fileId'];
        $this->jobName = $fileMetaData['jobName'];
        $this->receiptId = $receiptMetaData['receiptId'];
        $this->merchantName = $receiptMetaData['merchantName'];
        $this->merchantAddress = $receiptMetaData['merchantAddress'] ?? null;
        $this->merchantVatNumber = $receiptMetaData['merchantVatID'] ?? null;

        Log::debug('(MatchMerchant) Cache data fetched successfully', [
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
        // Only create merchant if we have a name
        if (! empty($this->merchantName) && trim($this->merchantName) !== '') {
            $newMerchant = Merchant::create([
                'name' => $this->merchantName,
                'address' => $this->merchantAddress,
                'vat_number' => $this->merchantVatNumber,
            ]);

            $this->updateReceipt($newMerchant->id);

            Log::info("(MatchMerchant) [{$this->jobName}] - New merchant created (receipt: {$this->receiptId})", [
                'merchant_id' => $newMerchant->id,
                'merchant_name' => $this->merchantName,
            ]);
        } else {
            Log::info("(MatchMerchant) [{$this->jobName}] - Skipping merchant creation due to empty name (receipt: {$this->receiptId})");
        }
    }
}
