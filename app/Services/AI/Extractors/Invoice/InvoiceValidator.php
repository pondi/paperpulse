<?php

namespace App\Services\AI\Extractors\Invoice;

/**
 * Validates extracted invoice data.
 */
class InvoiceValidator
{
    /**
     * Validate invoice data.
     *
     * @param  array  $data  Extracted invoice data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['vendor_name'])) {
            $errors[] = 'Missing vendor name';
        }

        if (empty($data['invoice_number'])) {
            $errors[] = 'Missing invoice number';
        }

        if (empty($data['invoice_date'])) {
            $errors[] = 'Missing invoice date';
        }

        if (empty($data['total_amount']) && ! isset($data['total_amount'])) {
            $errors[] = 'Missing total amount';
        }

        // Validate date formats
        if (! empty($data['invoice_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['invoice_date'])) {
            $warnings[] = 'Invoice date not in YYYY-MM-DD format';
        }

        if (! empty($data['due_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
            $warnings[] = 'Due date not in YYYY-MM-DD format';
        }

        if (! empty($data['delivery_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['delivery_date'])) {
            $warnings[] = 'Delivery date not in YYYY-MM-DD format';
        }

        // Validate amounts are numeric
        if (isset($data['total_amount']) && ! is_numeric($data['total_amount'])) {
            $errors[] = 'Total amount must be numeric';
        }

        if (isset($data['subtotal']) && ! is_numeric($data['subtotal'])) {
            $warnings[] = 'Subtotal must be numeric';
        }

        if (isset($data['tax_amount']) && ! is_numeric($data['tax_amount'])) {
            $warnings[] = 'Tax amount must be numeric';
        }

        // Validate line items if present
        if (! empty($data['line_items']) && is_array($data['line_items'])) {
            foreach ($data['line_items'] as $index => $item) {
                if (empty($item['description'])) {
                    $warnings[] = "Line item {$index}: missing description";
                }

                if (! isset($item['total_amount']) || ! is_numeric($item['total_amount'])) {
                    $warnings[] = "Line item {$index}: invalid or missing total amount";
                }
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
