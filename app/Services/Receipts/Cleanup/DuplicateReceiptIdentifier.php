<?php

namespace App\Services\Receipts\Cleanup;

use App\Models\Receipt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Identifies duplicate receipts in the system.
 * Single responsibility: Find receipts with duplicate file_ids.
 */
class DuplicateReceiptIdentifier
{
    /**
     * Find all duplicate receipts grouped by file_id.
     *
     * @return Collection Collection of file_ids with their duplicate receipts
     */
    public static function findDuplicates(): Collection
    {
        $duplicateFileIds = Receipt::select('file_id')
            ->groupBy('file_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('file_id');

        return Receipt::whereIn('file_id', $duplicateFileIds)
            ->orderBy('file_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('file_id');
    }

    /**
     * Find duplicate receipts for a specific file.
     *
     * @param int $fileId The file ID to check for duplicates
     * @return Collection Collection of duplicate receipts for the file
     */
    public static function findDuplicatesForFile(int $fileId): Collection
    {
        return Receipt::where('file_id', $fileId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}