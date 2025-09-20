<?php

namespace App\Services\Receipts\Analysis;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handles fallback date logic when receipt date cannot be extracted.
 * Provides a current date as fallback to allow receipt processing to continue.
 */
class DateFallbackHandler
{
    /**
     * Get a fallback date when extraction fails.
     * Uses current date to allow processing to continue.
     */
    public static function getFallbackDate(int $fileId, array $data): Carbon
    {
        $fallbackDate = Carbon::now();

        Log::warning('[DateFallbackHandler] Using fallback date for receipt', [
            'file_id' => $fileId,
            'fallback_date' => $fallbackDate->toDateString(),
            'reason' => 'Date could not be extracted from receipt',
            'merchant' => $data['merchant']['name'] ?? 'Unknown',
        ]);

        return $fallbackDate;
    }

    /**
     * Check if date extraction was successful.
     */
    public static function isDateExtractionSuccessful(?Carbon $dateTime): bool
    {
        return $dateTime !== null;
    }

    /**
     * Log date extraction failure for monitoring.
     */
    public static function logExtractionFailure(int $fileId, array $data): void
    {
        Log::info('[DateFallbackHandler] Date extraction failed - using fallback', [
            'file_id' => $fileId,
            'receipt_info' => $data['receipt_info'] ?? null,
            'receipt_data' => isset($data['receipt']) ? array_keys($data['receipt']) : null,
            'data_keys' => array_keys($data),
        ]);
    }
}
