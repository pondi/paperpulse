<?php

namespace App\Services\Receipts\Cleanup;

use App\Models\Receipt;
use Illuminate\Database\Eloquent\Collection;

/**
 * Determines which receipt to keep when duplicates exist.
 * Single responsibility: Select the best receipt from duplicates.
 */
class ReceiptSelectionStrategy
{
    /**
     * Select the best receipt to keep from a collection of duplicates.
     *
     * @param  Collection  $receipts  Collection of duplicate receipts
     * @return Receipt The receipt to keep
     */
    public static function selectBestReceipt(Collection $receipts): Receipt
    {
        if ($receipts->count() === 1) {
            return $receipts->first();
        }

        // Sort by priority:
        // 1. Receipts with actual extracted dates (not fallback)
        // 2. Receipts with more complete data (length of receipt_data)
        // 3. Most recent receipt
        return $receipts->sortByDesc(function (Receipt $receipt) {
            $score = 0;

            // Check if it has a real date (not using fallback)
            $receiptData = is_string($receipt->receipt_data)
                ? json_decode($receipt->receipt_data, true)
                : $receipt->receipt_data;

            $hasFallbackDate = ($receiptData['metadata']['fallback_date_used'] ?? false) === true;
            if (! $hasFallbackDate) {
                $score += 10000; // Heavily prioritize receipts with real dates
            }

            // Data completeness (length of JSON data)
            $score += strlen(json_encode($receipt->receipt_data));

            // Recency (as timestamp for minor scoring)
            $score += $receipt->created_at->timestamp / 1000000;

            return $score;
        })->first();
    }
}
