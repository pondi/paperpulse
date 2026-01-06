<?php

namespace App\Services\AI\Extractors\Contract;

/**
 * Validates extracted contract data.
 */
class ContractValidator
{
    /**
     * Validate contract data.
     *
     * @param  array  $data  Extracted contract data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['contract_title'])) {
            $errors[] = 'Missing contract title';
        }

        if (empty($data['effective_date'])) {
            $errors[] = 'Missing effective date';
        }

        if (empty($data['parties']) || ! is_array($data['parties'])) {
            $errors[] = 'Missing contract parties';
        } elseif (count($data['parties']) < 2) {
            $warnings[] = 'Contract should have at least two parties';
        }

        // Validate date formats
        if (! empty($data['effective_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['effective_date'])) {
            $warnings[] = 'Effective date not in YYYY-MM-DD format';
        }

        if (! empty($data['expiry_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expiry_date'])) {
            $warnings[] = 'Expiry date not in YYYY-MM-DD format';
        }

        if (! empty($data['signature_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['signature_date'])) {
            $warnings[] = 'Signature date not in YYYY-MM-DD format';
        }

        // Validate contract value is numeric if present
        if (isset($data['contract_value']) && ! is_numeric($data['contract_value'])) {
            $errors[] = 'Contract value must be numeric';
        }

        // Validate parties have required fields
        if (! empty($data['parties']) && is_array($data['parties'])) {
            foreach ($data['parties'] as $i => $party) {
                if (empty($party['name'])) {
                    $errors[] = "Party {$i} missing name";
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
