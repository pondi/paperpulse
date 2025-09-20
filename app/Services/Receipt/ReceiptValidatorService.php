<?php

namespace App\Services\Receipt;

use App\Contracts\Services\ReceiptValidatorContract;
use Illuminate\Support\Facades\Log;

class ReceiptValidatorService implements ReceiptValidatorContract
{
    /**
     * Validate parsed receipt data
     */
    public function validateParsedData(array $data, int $fileId): array
    {
        $errors = [];
        $warnings = [];

        // Validate merchant information
        $merchantValidation = $this->validateMerchantData($data);
        if (! $merchantValidation['valid']) {
            $errors = array_merge($errors, $merchantValidation['errors']);
        }

        // Validate totals
        $totalsValidation = $this->validateTotals($data);
        if (! $totalsValidation['valid']) {
            if ($totalsValidation['critical']) {
                $errors = array_merge($errors, $totalsValidation['errors']);
            } else {
                $warnings = array_merge($warnings, $totalsValidation['errors']);
            }
        }

        // Validate date/time
        $dateValidation = $this->validateDateTime($data);
        if (! $dateValidation['valid']) {
            $warnings = array_merge($warnings, $dateValidation['errors']);
        }

        // Validate currency
        $currencyValidation = $this->validateCurrency($data);
        if (! $currencyValidation['valid']) {
            $warnings = array_merge($warnings, $currencyValidation['errors']);
        }

        // Validate line items if present
        if (! empty($data['items'])) {
            $itemsValidation = $this->validateLineItems($data['items']);
            if (! $itemsValidation['valid']) {
                $warnings = array_merge($warnings, $itemsValidation['errors']);
            }
        }

        $isValid = empty($errors);

        Log::info('[ReceiptValidator] Validation completed', [
            'file_id' => $fileId,
            'valid' => $isValid,
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
        ]);

        if (! empty($errors)) {
            Log::warning('[ReceiptValidator] Validation errors', [
                'file_id' => $fileId,
                'errors' => $errors,
            ]);
        }

        return [
            'valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate merchant data
     */
    protected function validateMerchantData(array $data): array
    {
        $errors = [];

        $hasValidMerchant = ! empty($data['merchant']['name']) || ! empty($data['store']['name']);

        if (! $hasValidMerchant) {
            $errors[] = 'Missing merchant information - no merchant or store name found';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate totals data
     */
    protected function validateTotals(array $data): array
    {
        $errors = [];
        $critical = false;

        $hasValidTotal = ! empty($data['totals']['total_amount']) ||
                        ! empty($data['receipt']['total']) ||
                        ! empty($data['total']);

        if (! $hasValidTotal) {
            $errors[] = 'Missing total amount - no valid total found in receipt data';
            // This is not critical as we can still process without total
        }

        // Validate that total is numeric and positive
        $total = $data['totals']['total_amount'] ??
                $data['receipt']['total'] ??
                $data['total'] ?? null;

        if ($total !== null) {
            if (! is_numeric($total)) {
                $errors[] = "Total amount is not numeric: {$total}";
            } elseif ((float) $total < 0) {
                $errors[] = "Total amount is negative: {$total}";
            } elseif ((float) $total > 999999.99) {
                $errors[] = "Total amount seems unreasonably large: {$total}";
            }
        }

        // Validate tax amount if present
        $taxAmount = $data['totals']['tax_amount'] ??
                    $data['totals']['vat_amount'] ??
                    $data['tax_amount'] ?? null;

        if ($taxAmount !== null && ! is_numeric($taxAmount)) {
            $errors[] = "Tax amount is not numeric: {$taxAmount}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'critical' => $critical,
        ];
    }

    /**
     * Validate date/time data
     */
    protected function validateDateTime(array $data): array
    {
        $errors = [];

        $date = $data['receipt_info']['date'] ??
               $data['receipt']['date'] ??
               $data['date'] ?? null;

        if ($date && ! $this->isValidDate($date)) {
            $errors[] = "Invalid date format: {$date}";
        }

        $time = $data['receipt_info']['time'] ??
               $data['receipt']['time'] ??
               $data['time'] ?? null;

        if ($time && ! $this->isValidTime($time)) {
            $errors[] = "Invalid time format: {$time}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate currency data
     */
    protected function validateCurrency(array $data): array
    {
        $errors = [];

        $currency = $data['payment']['currency'] ??
                   $data['currency'] ??
                   $data['totals']['currency'] ?? null;

        if ($currency && ! $this->isValidCurrencyCode($currency)) {
            $errors[] = "Invalid currency code: {$currency}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate line items
     */
    protected function validateLineItems(array $items): array
    {
        $errors = [];

        if (! is_array($items)) {
            $errors[] = 'Line items should be an array';

            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $errors[] = "Line item {$index} should be an array";

                continue;
            }

            // Check for required fields
            $hasName = ! empty($item['name']) || ! empty($item['description']);
            if (! $hasName) {
                $errors[] = "Line item {$index} missing name or description";
            }

            // Validate price if present
            if (isset($item['price']) && ! is_numeric($item['price'])) {
                $errors[] = "Line item {$index} has invalid price: {$item['price']}";
            }

            if (isset($item['unit_price']) && ! is_numeric($item['unit_price'])) {
                $errors[] = "Line item {$index} has invalid unit price: {$item['unit_price']}";
            }

            // Validate quantity if present
            if (isset($item['quantity']) && ! is_numeric($item['quantity'])) {
                $errors[] = "Line item {$index} has invalid quantity: {$item['quantity']}";
            }

            // Validate total if present
            if (isset($item['total']) && ! is_numeric($item['total'])) {
                $errors[] = "Line item {$index} has invalid total: {$item['total']}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if date string is valid
     */
    protected function isValidDate(string $date): bool
    {
        return (bool) strtotime($date);
    }

    /**
     * Check if time string is valid
     */
    protected function isValidTime(string $time): bool
    {
        return (bool) strtotime($time);
    }

    /**
     * Check if currency code is valid
     */
    protected function isValidCurrencyCode(string $currency): bool
    {
        $validCurrencies = [
            'NOK', 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF',
            'SEK', 'DKK', 'PLN', 'CZK', 'HUF', 'RUB', 'CNY', 'INR',
        ];

        return in_array(strtoupper($currency), $validCurrencies);
    }

    /**
     * Check if receipt data contains essential information for processing
     */
    public function hasEssentialData(array $data): bool
    {
        $hasValidMerchant = ! empty($data['merchant']['name']) || ! empty($data['store']['name']);

        return $hasValidMerchant;
    }

    /**
     * Sanitize and clean parsed data
     */
    public function sanitizeData(array $data): array
    {
        // Clean merchant name
        if (! empty($data['merchant']['name'])) {
            $data['merchant']['name'] = $this->cleanText($data['merchant']['name']);
        }

        // Clean total amounts
        if (isset($data['totals']['total_amount'])) {
            $data['totals']['total_amount'] = $this->cleanNumeric($data['totals']['total_amount']);
        }

        if (isset($data['totals']['tax_amount'])) {
            $data['totals']['tax_amount'] = $this->cleanNumeric($data['totals']['tax_amount']);
        }

        // Clean line items
        if (! empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['name'])) {
                    $item['name'] = $this->cleanText($item['name']);
                }
                if (isset($item['price'])) {
                    $item['price'] = $this->cleanNumeric($item['price']);
                }
                if (isset($item['quantity'])) {
                    $item['quantity'] = $this->cleanNumeric($item['quantity']);
                }
            }
        }

        return $data;
    }

    /**
     * Clean text values
     */
    protected function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Clean numeric values
     */
    protected function cleanNumeric($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Try to extract numeric value from string
        $cleaned = preg_replace('/[^\d.]/', '', (string) $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0;
    }
}
