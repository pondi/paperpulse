<?php

namespace App\Contracts\Services;

interface ReceiptValidatorContract
{
    /**
     * Validate parsed receipt data
     */
    public function validateParsedData(array $data, int $fileId): array;

    /**
     * Check if receipt data contains essential information for processing
     */
    public function hasEssentialData(array $data): bool;

    /**
     * Sanitize and clean parsed data
     */
    public function sanitizeData(array $data): array;
}
