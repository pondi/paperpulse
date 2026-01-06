<?php

namespace App\Services\AI\Extractors\Document;

/**
 * Validates extracted document data.
 *
 * Generic document validation with minimal requirements since this is a fallback extractor.
 */
class DocumentValidator
{
    /**
     * Validate document data.
     *
     * @param  array  $data  Extracted document data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required field: document_title
        if (empty($data['document_title'])) {
            $errors[] = 'Missing document title';
        }

        // Validate date format if present
        if (! empty($data['creation_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['creation_date'])) {
            $warnings[] = 'Creation date not in YYYY-MM-DD format';
        }

        // Check confidence score
        if (isset($data['confidence_score']) && $data['confidence_score'] < 0.5) {
            $warnings[] = 'Low confidence score: '.$data['confidence_score'];
        }

        // Warn if minimal content (no summary or key points)
        if (empty($data['summary']) && empty($data['key_points'])) {
            $warnings[] = 'Document has no summary or key points';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
