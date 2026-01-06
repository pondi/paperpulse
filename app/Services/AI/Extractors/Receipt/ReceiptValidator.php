<?php

namespace App\Services\AI\Extractors\Receipt;

/**
 * Validates extracted receipt data.
 */
class ReceiptValidator
{
    /**
     * Validate receipt data.
     *
     * @param  array  $data  Extracted receipt data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['merchant_name'])) {
            $errors[] = 'Missing merchant name';
        }

        if (empty($data['total_amount']) && ! isset($data['total_amount'])) {
            $errors[] = 'Missing total amount';
        }

        if (empty($data['receipt_date'])) {
            $errors[] = 'Missing receipt date';
        }

        // Validate date format
        if (! empty($data['receipt_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['receipt_date'])) {
            $warnings[] = 'Receipt date not in YYYY-MM-DD format';
        }

        // Validate amounts are numeric
        if (isset($data['total_amount']) && ! is_numeric($data['total_amount'])) {
            $errors[] = 'Total amount must be numeric';
        }

        // Check confidence score
        if (isset($data['confidence_score']) && $data['confidence_score'] < 0.5) {
            $warnings[] = 'Low confidence score: '.$data['confidence_score'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
