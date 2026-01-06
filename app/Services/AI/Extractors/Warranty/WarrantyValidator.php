<?php

namespace App\Services\AI\Extractors\Warranty;

/**
 * Validates extracted warranty data.
 */
class WarrantyValidator
{
    /**
     * Validate warranty data.
     *
     * @param  array  $data  Extracted warranty data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['provider_name'])) {
            $errors[] = 'Missing warranty provider name';
        }

        if (empty($data['product_name'])) {
            $errors[] = 'Missing product name';
        }

        if (empty($data['warranty_end_date'])) {
            $errors[] = 'Missing warranty end date';
        }

        // Validate date formats
        if (! empty($data['purchase_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['purchase_date'])) {
            $warnings[] = 'Purchase date not in YYYY-MM-DD format';
        }

        if (! empty($data['warranty_start_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['warranty_start_date'])) {
            $warnings[] = 'Warranty start date not in YYYY-MM-DD format';
        }

        if (! empty($data['warranty_end_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['warranty_end_date'])) {
            $warnings[] = 'Warranty end date not in YYYY-MM-DD format';
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
