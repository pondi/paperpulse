<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptParserContract;
use App\Models\ExtractableEntity;
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
        $originalDateTime = $parser->extractDateTime($data);
        $dateExtractionFailed = ! DateFallbackHandler::isDateExtractionSuccessful($originalDateTime);

        if ($dateExtractionFailed) {
            $receiptData = $payload['receipt_data'] ?? [];
            $receiptData['metadata'] = array_merge($receiptData['metadata'] ?? [], [
                'needs_date_update' => true,
                'date_extraction_failed' => true,
                'fallback_date_used' => Carbon::now()->toDateString(),
            ]);
            $payload['receipt_data'] = $receiptData;
        }

        $receipt = Receipt::create($payload);
        ExtractableEntity::create([
            'file_id' => $receipt->file_id, 'user_id' => $receipt->user_id,
            'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true,
            'extraction_provider' => 'textract_openai', 'extracted_at' => now(),
        ]);

        if ($dateExtractionFailed) {
            DateUpdateNotifier::markForDateUpdate($receipt);
        }

        return $receipt;
    }
}
