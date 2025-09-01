<?php

namespace App\Contracts\Services;

use Carbon\Carbon;

interface ReceiptParserContract
{
    /**
     * Parse receipt content using AI
     */
    public function parseReceipt(string $content, int $fileId): array;

    /**
     * Extract merchant information from parsed data
     */
    public function extractMerchantData(array $data): array;

    /**
     * Extract date/time from parsed data
     */
    public function extractDateTime(array $data): ?Carbon;

    /**
     * Extract totals from parsed data
     */
    public function extractTotals(array $data): array;

    /**
     * Extract currency from parsed data
     */
    public function extractCurrency(array $data, string $default = 'NOK'): string;

    /**
     * Extract line items from parsed data
     */
    public function extractItems(array $data): array;

    /**
     * Extract merchant information directly from content
     */
    public function extractMerchantInfo(string $content): array;

    /**
     * Generate description from parsed data
     */
    public function generateDescription(array $data, string $defaultCurrency = 'NOK'): string;
}
