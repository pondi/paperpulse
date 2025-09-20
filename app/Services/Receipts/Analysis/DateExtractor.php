<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptParserContract;
use Carbon\Carbon;

/**
 * Extracts and processes date/time from receipt data.
 * Single responsibility: Handle all date extraction logic.
 */
class DateExtractor
{
    /**
     * Extract date/time from receipt data with fallback support.
     */
    public static function extract(
        array $data,
        ReceiptParserContract $parser,
        int $fileId
    ): Carbon {
        // Try to extract date using parser
        $dateTime = $parser->extractDateTime($data);

        // If extraction failed, use fallback
        if (! DateFallbackHandler::isDateExtractionSuccessful($dateTime)) {
            DateFallbackHandler::logExtractionFailure($fileId, $data);
            $dateTime = DateFallbackHandler::getFallbackDate($fileId, $data);
        }

        return $dateTime;
    }
}
