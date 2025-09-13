<?php

namespace App\Services\Receipts\Cleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleans up duplicate receipts from the system.
 * Single responsibility: Remove duplicate receipts while preserving the best one.
 */
class DuplicateReceiptCleaner
{
    /**
     * Clean duplicate receipts for a specific file.
     *
     * @param  int  $fileId  The file ID to clean duplicates for
     * @return array Cleanup results
     */
    public static function cleanForFile(int $fileId): array
    {
        $duplicates = DuplicateReceiptIdentifier::findDuplicatesForFile($fileId);

        if ($duplicates->count() <= 1) {
            return [
                'file_id' => $fileId,
                'duplicates_found' => 0,
                'receipts_deleted' => 0,
                'kept_receipt_id' => $duplicates->first()?->id,
            ];
        }

        $receiptToKeep = ReceiptSelectionStrategy::selectBestReceipt($duplicates);
        $receiptsToDelete = $duplicates->reject(fn ($r) => $r->id === $receiptToKeep->id);

        $deletedCount = 0;
        DB::transaction(function () use ($receiptsToDelete, &$deletedCount) {
            foreach ($receiptsToDelete as $receipt) {
                $receipt->lineItems()->delete();
                $receipt->delete();
                $deletedCount++;
            }
        });

        Log::info('[DuplicateReceiptCleaner] Cleaned duplicates for file', [
            'file_id' => $fileId,
            'kept_receipt_id' => $receiptToKeep->id,
            'deleted_count' => $deletedCount,
        ]);

        return [
            'file_id' => $fileId,
            'duplicates_found' => $duplicates->count(),
            'receipts_deleted' => $deletedCount,
            'kept_receipt_id' => $receiptToKeep->id,
        ];
    }

    /**
     * Clean all duplicate receipts in the system.
     *
     * @return array Cleanup results
     */
    public static function cleanAll(): array
    {
        $duplicateGroups = DuplicateReceiptIdentifier::findDuplicates();
        $results = [];

        foreach ($duplicateGroups as $fileId => $receipts) {
            $results[] = self::cleanForFile($fileId);
        }

        return $results;
    }
}
