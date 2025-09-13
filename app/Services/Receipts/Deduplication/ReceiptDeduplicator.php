<?php

namespace App\Services\Receipts\Deduplication;

use App\Contracts\Services\ReceiptParserContract;
use App\Models\Receipt;
use App\Services\Receipts\Analysis\ReceiptCreator;
use Illuminate\Support\Facades\Log;

/**
 * Handles receipt deduplication to prevent creating duplicates.
 * Single responsibility: Manage deduplication logic for receipts.
 */
class ReceiptDeduplicator
{
    /**
     * Check if a new receipt should be created for the file.
     */
    public static function shouldCreateReceipt(int $fileId): bool
    {
        return ! ReceiptExistenceChecker::exists($fileId);
    }

    /**
     * Get existing receipt or create a new one if it doesn't exist.
     */
    public static function getOrCreate(
        array $payload,
        array $data,
        ReceiptParserContract $parser
    ): Receipt {
        $fileId = $payload['file_id'] ?? null;

        if (! $fileId) {
            // No file_id means we can't check for duplicates
            return ReceiptCreator::create($payload, $data, $parser);
        }

        $existing = ReceiptExistenceChecker::findExisting($fileId);

        if ($existing) {
            Log::info('[ReceiptDeduplicator] Receipt already exists for file', [
                'file_id' => $fileId,
                'receipt_id' => $existing->id,
                'created_at' => $existing->created_at,
            ]);

            return $existing;
        }

        // No existing receipt, create a new one
        $receipt = ReceiptCreator::create($payload, $data, $parser);

        Log::info('[ReceiptDeduplicator] Created new receipt', [
            'file_id' => $fileId,
            'receipt_id' => $receipt->id,
        ]);

        return $receipt;
    }
}
