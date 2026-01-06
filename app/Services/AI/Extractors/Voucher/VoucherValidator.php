<?php

namespace App\Services\AI\Extractors\Voucher;

/**
 * Validates extracted voucher data.
 */
class VoucherValidator
{
    /**
     * Validate voucher data.
     *
     * @param  array  $data  Extracted voucher data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['issuer_name'])) {
            $errors[] = 'Missing issuer name';
        }

        if (empty($data['voucher_code'])) {
            $errors[] = 'Missing voucher code';
        }

        if (empty($data['expiry_date'])) {
            $errors[] = 'Missing expiry date';
        }

        if (empty($data['value_amount']) && ! isset($data['value_amount'])) {
            $errors[] = 'Missing value amount';
        }

        // Validate date format
        if (! empty($data['issue_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['issue_date'])) {
            $warnings[] = 'Issue date not in YYYY-MM-DD format';
        }

        if (! empty($data['expiry_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expiry_date'])) {
            $warnings[] = 'Expiry date not in YYYY-MM-DD format';
        }

        // Validate amounts are numeric
        if (isset($data['value_amount']) && ! is_numeric($data['value_amount'])) {
            $errors[] = 'Value amount must be numeric';
        }

        // Check if voucher is expired
        if (! empty($data['expiry_date'])) {
            $expiryDate = strtotime($data['expiry_date']);
            if ($expiryDate && $expiryDate < time()) {
                $warnings[] = 'Voucher has expired';
            }
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
