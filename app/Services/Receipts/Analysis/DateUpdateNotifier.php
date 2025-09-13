<?php

namespace App\Services\Receipts\Analysis;

use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

/**
 * Handles notifications for receipts that need date updates.
 * Single responsibility: Track and notify about receipts with fallback dates.
 */
class DateUpdateNotifier
{
    /**
     * Mark receipt as needing date update.
     */
    public static function markForDateUpdate(Receipt $receipt): void
    {
        // Add metadata to receipt_data to indicate date needs update
        $receiptData = $receipt->receipt_data ?? [];
        $receiptData['metadata'] = array_merge($receiptData['metadata'] ?? [], [
            'needs_date_update' => true,
            'date_extraction_failed' => true,
            'fallback_date_used' => true,
        ]);
        $receipt->receipt_data = $receiptData;
        $receipt->save();

        Log::info('[DateUpdateNotifier] Receipt marked for date update', [
            'receipt_id' => $receipt->id,
            'current_date' => $receipt->receipt_date,
        ]);
    }

    /**
     * Check if receipt needs date update.
     */
    public static function needsDateUpdate(Receipt $receipt): bool
    {
        $receiptData = $receipt->receipt_data ?? [];
        $metadata = $receiptData['metadata'] ?? [];

        return ($metadata['needs_date_update'] ?? false) === true;
    }

    /**
     * Clear date update flag after successful update.
     */
    public static function clearDateUpdateFlag(Receipt $receipt): void
    {
        $receiptData = $receipt->receipt_data ?? [];
        if (! empty($receiptData['metadata'])) {
            $metadata = $receiptData['metadata'];
            unset($metadata['needs_date_update']);
            unset($metadata['date_extraction_failed']);
            unset($metadata['fallback_date_used']);
            $receiptData['metadata'] = $metadata;
            $receipt->receipt_data = $receiptData;
            $receipt->save();

            Log::info('[DateUpdateNotifier] Date update flag cleared', [
                'receipt_id' => $receipt->id,
            ]);
        }
    }
}
