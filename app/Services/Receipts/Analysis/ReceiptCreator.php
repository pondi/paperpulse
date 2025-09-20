<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptParserContract;
use App\Models\Receipt;
use Carbon\Carbon;

/**
 * Creates receipt records with proper metadata tracking.
 * Single responsibility: Handle receipt creation with metadata.
 */
class ReceiptCreator
{
    /**
     * Create receipt with date extraction status tracking.
     */
    public static function create(array $payload, array $data, ReceiptParserContract $parser): Receipt
    {
        // Check if date extraction failed (fallback was used)
        $originalDateTime = $parser->extractDateTime($data);
        $dateExtractionFailed = ! DateFallbackHandler::isDateExtractionSuccessful($originalDateTime);

        // Add metadata if date extraction failed
        if ($dateExtractionFailed) {
            $receiptData = json_decode($payload['receipt_data'] ?? '{}', true);
            $receiptData['metadata'] = array_merge($receiptData['metadata'] ?? [], [
                'needs_date_update' => true,
                'date_extraction_failed' => true,
                'fallback_date_used' => Carbon::now()->toDateString(),
            ]);
            $payload['receipt_data'] = json_encode($receiptData);
        }

        // Create the receipt
        $receipt = Receipt::create($payload);

        // Mark for date update if extraction failed
        if ($dateExtractionFailed) {
            DateUpdateNotifier::markForDateUpdate($receipt);
        }

        return $receipt;
    }
}
