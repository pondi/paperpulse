<?php

namespace App\Services\Receipts\Deduplication;

use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

/**
 * Logs deduplication events for monitoring and auditing.
 * Single responsibility: Record deduplication activities.
 */
class DeduplicationLogger
{
    /**
     * Log when a duplicate receipt creation was prevented.
     */
    public static function logDuplicatePrevented(int $fileId, Receipt $existing): void
    {
        Log::info('[DeduplicationLogger] Duplicate receipt prevented', [
            'file_id' => $fileId,
            'existing_receipt_id' => $existing->id,
            'existing_created_at' => $existing->created_at->toDateTimeString(),
            'prevention_timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log when cleanup has been performed.
     */
    public static function logCleanupPerformed(array $results): void
    {
        $totalDeleted = collect($results)->sum('receipts_deleted');
        $filesProcessed = count($results);

        Log::info('[DeduplicationLogger] Cleanup performed', [
            'files_processed' => $filesProcessed,
            'total_receipts_deleted' => $totalDeleted,
            'cleanup_timestamp' => now()->toDateTimeString(),
            'results' => $results,
        ]);
    }
}
