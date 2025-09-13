<?php

namespace App\Services\Receipts\Deduplication;

use App\Models\Receipt;

/**
 * Checks for existing receipts to prevent duplicates.
 * Single responsibility: Verify receipt existence for a file.
 */
class ReceiptExistenceChecker
{
    /**
     * Check if a receipt already exists for the given file.
     */
    public static function exists(int $fileId): bool
    {
        return Receipt::where('file_id', $fileId)->exists();
    }

    /**
     * Find an existing receipt for the given file.
     */
    public static function findExisting(int $fileId): ?Receipt
    {
        return Receipt::where('file_id', $fileId)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}