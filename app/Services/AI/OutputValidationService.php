<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OutputValidationService
{
    protected array $validationRules = [];

    protected array $customValidators = [];

    public function __construct()
    {
        $this->initializeValidationRules();
        $this->registerCustomValidators();
    }

    /**
     * Validate AI output against schema and business rules
     */
    public function validateOutput(array $data, string $type, array $schema = [], array $options = []): ValidationResult
    {
        try {
            // Step 1: Schema validation
            $schemaValidation = $this->validateAgainstSchema($data, $schema, $type);
            if (! $schemaValidation->isValid) {
                return $schemaValidation;
            }

            // Step 2: Business logic validation
            $businessValidation = $this->validateBusinessLogic($data, $type, $options);
            if (! $businessValidation->isValid) {
                return $businessValidation;
            }

            // Step 3: Data quality checks
            $qualityValidation = $this->validateDataQuality($data, $type, $options);
            if (! $qualityValidation->isValid) {
                return $qualityValidation;
            }

            // Step 4: Apply transformations and sanitization
            $sanitizedData = $this->sanitizeData($data, $type);

            return ValidationResult::success($sanitizedData, [
                'validation_type' => $type,
                'rules_applied' => $this->getAppliedRules($type),
                'transformations' => $this->getAppliedTransformations($data, $sanitizedData),
            ]);

        } catch (Exception $e) {
            Log::error('[OutputValidationService] Validation failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);

            return ValidationResult::failure("Validation error: {$e->getMessage()}", [
                'exception' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Validate against JSON schema
     */
    protected function validateAgainstSchema(array $data, array $schema, string $type): ValidationResult
    {
        if (empty($schema)) {
            return ValidationResult::success($data, ['schema_validation' => 'skipped']);
        }

        $errors = $this->validateJsonSchema($data, $schema);

        if (! empty($errors)) {
            return ValidationResult::failure('Schema validation failed', [
                'schema_errors' => $errors,
                'validation_type' => 'schema',
            ]);
        }

        return ValidationResult::success($data, ['schema_validation' => 'passed']);
    }

    /**
     * Validate business logic rules
     */
    protected function validateBusinessLogic(array $data, string $type, array $options = []): ValidationResult
    {
        $rules = $this->validationRules[$type] ?? [];

        if (empty($rules)) {
            return ValidationResult::success($data, ['business_validation' => 'no_rules']);
        }

        try {
            $validator = Validator::make($data, $rules, $this->getCustomMessages($type));

            if ($validator->fails()) {
                return ValidationResult::failure('Business logic validation failed', [
                    'validation_errors' => $validator->errors()->toArray(),
                    'validation_type' => 'business_logic',
                ]);
            }

            return ValidationResult::success($data, ['business_validation' => 'passed']);

        } catch (Exception $e) {
            return ValidationResult::failure("Business validation error: {$e->getMessage()}");
        }
    }

    /**
     * Validate data quality and consistency
     */
    protected function validateDataQuality(array $data, string $type, array $options = []): ValidationResult
    {
        $issues = [];

        // Type-specific quality checks
        switch ($type) {
            case 'receipt':
                $issues = array_merge($issues, $this->validateReceiptQuality($data, $options));
                break;
            case 'document':
                $issues = array_merge($issues, $this->validateDocumentQuality($data, $options));
                break;
        }

        // Generic quality checks
        $issues = array_merge($issues, $this->validateGenericQuality($data, $options));

        if (! empty($issues)) {
            // Determine if issues are warnings or errors
            $errors = array_filter($issues, fn ($issue) => $issue['level'] === 'error');
            $warnings = array_filter($issues, fn ($issue) => $issue['level'] === 'warning');

            if (! empty($errors)) {
                return ValidationResult::failure('Data quality validation failed', [
                    'quality_errors' => $errors,
                    'quality_warnings' => $warnings,
                    'validation_type' => 'quality',
                ]);
            }

            return ValidationResult::success($data, [
                'quality_validation' => 'passed_with_warnings',
                'quality_warnings' => $warnings,
            ]);
        }

        return ValidationResult::success($data, ['quality_validation' => 'passed']);
    }

    /**
     * Receipt-specific quality validation
     */
    protected function validateReceiptQuality(array $data, array $options = []): array
    {
        $issues = [];

        // Check if totals add up
        if (isset($data['items']) && isset($data['totals']['total_amount'])) {
            $itemsTotal = array_sum(array_column($data['items'], 'total_price'));
            $declaredTotal = $data['totals']['total_amount'];

            $difference = abs($itemsTotal - $declaredTotal);
            if ($difference > 0.01) { // Allow 1 cent rounding difference
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'calculation_mismatch',
                    'message' => "Items total ({$itemsTotal}) doesn't match declared total ({$declaredTotal})",
                    'difference' => $difference,
                ];
            }
        }

        // Check for reasonable VAT rates (Norwegian context)
        if (isset($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                if (isset($item['vat_rate'])) {
                    $vatRate = $item['vat_rate'];
                    $validRates = [0, 0.12, 0.15, 0.25]; // Norwegian VAT rates

                    if (! in_array($vatRate, $validRates, true)) {
                        $issues[] = [
                            'level' => 'warning',
                            'type' => 'invalid_vat_rate',
                            'message' => "Unusual VAT rate {$vatRate} for item {$index}",
                            'item_index' => $index,
                            'vat_rate' => $vatRate,
                        ];
                    }
                }
            }
        }

        // Check merchant organization number format (Norwegian)
        if (isset($data['merchant']['org_number'])) {
            $orgNumber = $data['merchant']['org_number'];
            if (! preg_match('/^\d{9}$/', $orgNumber)) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'invalid_org_number',
                    'message' => "Organization number '{$orgNumber}' doesn't match Norwegian format",
                    'org_number' => $orgNumber,
                ];
            }
        }

        // Check date format and reasonableness
        if (isset($data['receipt_info']['date'])) {
            $date = $data['receipt_info']['date'];
            if (! $this->isValidDate($date)) {
                $issues[] = [
                    'level' => 'error',
                    'type' => 'invalid_date',
                    'message' => "Invalid receipt date format: {$date}",
                    'date' => $date,
                ];
            } elseif ($this->isDateInFuture($date)) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'future_date',
                    'message' => "Receipt date is in the future: {$date}",
                    'date' => $date,
                ];
            } elseif ($this->isDateTooOld($date, 10)) { // 10 years
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'very_old_date',
                    'message' => "Receipt date is very old: {$date}",
                    'date' => $date,
                ];
            }
        }

        return $issues;
    }

    /**
     * Document-specific quality validation
     */
    protected function validateDocumentQuality(array $data, array $options = []): array
    {
        $issues = [];

        // Check summary length and quality
        if (isset($data['summary'])) {
            $summary = $data['summary'];
            $wordCount = str_word_count($summary);

            if ($wordCount < 3) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'short_summary',
                    'message' => "Summary is too short ({$wordCount} words)",
                    'word_count' => $wordCount,
                ];
            } elseif ($wordCount > 100) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'long_summary',
                    'message' => "Summary is quite long ({$wordCount} words)",
                    'word_count' => $wordCount,
                ];
            }
        }

        // Check entity consistency
        if (isset($data['entities'])) {
            foreach ($data['entities'] as $entityType => $entities) {
                if (is_array($entities)) {
                    $duplicates = array_diff_assoc($entities, array_unique($entities));
                    if (! empty($duplicates)) {
                        $issues[] = [
                            'level' => 'warning',
                            'type' => 'duplicate_entities',
                            'message' => "Duplicate {$entityType} entities found",
                            'entity_type' => $entityType,
                            'duplicates' => $duplicates,
                        ];
                    }
                }
            }
        }

        // Check tag relevance (basic check)
        if (isset($data['tags']) && is_array($data['tags'])) {
            $tagCount = count($data['tags']);
            if ($tagCount === 0) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'no_tags',
                    'message' => 'No tags were extracted from the document',
                ];
            } elseif ($tagCount > 15) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'too_many_tags',
                    'message' => "Many tags extracted ({$tagCount}), may lack focus",
                    'tag_count' => $tagCount,
                ];
            }
        }

        return $issues;
    }

    /**
     * Generic quality validation
     */
    protected function validateGenericQuality(array $data, array $options = []): array
    {
        $issues = [];

        // Check for empty required fields
        $requiredFields = $options['required_fields'] ?? [];
        foreach ($requiredFields as $field) {
            if (! $this->hasNonEmptyValue($data, $field)) {
                $issues[] = [
                    'level' => 'error',
                    'type' => 'missing_required_field',
                    'message' => "Required field '{$field}' is missing or empty",
                    'field' => $field,
                ];
            }
        }

        // Check for suspicious values
        $this->checkForSuspiciousValues($data, $issues);

        return $issues;
    }

    /**
     * Sanitize and transform data
     */
    protected function sanitizeData(array $data, string $type): array
    {
        $sanitized = $data;

        // Type-specific sanitization
        switch ($type) {
            case 'receipt':
                $sanitized = $this->sanitizeReceiptData($sanitized);
                break;
            case 'document':
                $sanitized = $this->sanitizeDocumentData($sanitized);
                break;
        }

        // Generic sanitization
        $sanitized = $this->sanitizeGenericData($sanitized);

        return $sanitized;
    }

    /**
     * Receipt-specific data sanitization
     */
    protected function sanitizeReceiptData(array $data): array
    {
        // Normalize prices to 2 decimal places
        if (isset($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['unit_price'])) {
                    $item['unit_price'] = round($item['unit_price'], 2);
                }
                if (isset($item['total_price'])) {
                    $item['total_price'] = round($item['total_price'], 2);
                }
            }
        }

        if (isset($data['totals'])) {
            foreach ($data['totals'] as $key => &$value) {
                if (is_numeric($value)) {
                    $value = round($value, 2);
                }
            }
        }

        // Normalize date format
        if (isset($data['receipt_info']['date'])) {
            $data['receipt_info']['date'] = $this->normalizeDate($data['receipt_info']['date']);
        }

        // Clean organization number
        if (isset($data['merchant']['org_number'])) {
            $data['merchant']['org_number'] = preg_replace('/\D/', '', $data['merchant']['org_number']);
        }

        return $data;
    }

    /**
     * Document-specific data sanitization
     */
    protected function sanitizeDocumentData(array $data): array
    {
        // Trim and clean text fields
        $textFields = ['title', 'summary'];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim($data[$field]);
                $data[$field] = preg_replace('/\s+/', ' ', $data[$field]); // Normalize whitespace
            }
        }

        // Clean and deduplicate arrays
        $arrayFields = ['tags'];
        foreach ($arrayFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_unique(array_filter(array_map('trim', $data[$field])));
                $data[$field] = array_values($data[$field]); // Reindex
            }
        }

        // Clean entities
        if (isset($data['entities']) && is_array($data['entities'])) {
            foreach ($data['entities'] as $entityType => &$entities) {
                if (is_array($entities)) {
                    $entities = array_unique(array_filter(array_map('trim', $entities)));
                    $entities = array_values($entities); // Reindex
                }
            }
        }

        return $data;
    }

    /**
     * Generic data sanitization
     */
    protected function sanitizeGenericData(array $data): array
    {
        return $this->recursivelyCleanData($data);
    }

    /**
     * Recursively clean data
     */
    protected function recursivelyCleanData($data)
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanedValue = $this->recursivelyCleanData($value);
                if ($cleanedValue !== null && $cleanedValue !== '') {
                    $cleaned[$key] = $cleanedValue;
                }
            }

            return $cleaned;
        }

        if (is_string($data)) {
            return trim($data);
        }

        return $data;
    }

    // Helper methods
    protected function initializeValidationRules(): void
    {
        $this->validationRules = [
            'receipt' => [
                'merchant' => 'required|array',
                'merchant.name' => 'required|string|min:1',
                'totals' => 'required|array',
                'totals.total_amount' => 'required|numeric|min:0',
                'receipt_info' => 'required|array',
                'items' => 'sometimes|array',
                'items.*.name' => 'required_with:items|string',
                'items.*.total_price' => 'required_with:items|numeric|min:0',
            ],
            'document' => [
                'title' => 'required|string|min:1',
                'document_type' => 'required|string',
                'summary' => 'required|string|min:3',
                'language' => 'required|string|size:2',
            ],
        ];
    }

    protected function registerCustomValidators(): void
    {
        // Register custom validation rules if needed
    }

    protected function getCustomMessages(string $type): array
    {
        return [
            'receipt' => [
                'merchant.name.required' => 'Merchant name is required',
                'totals.total_amount.required' => 'Total amount is required',
                'totals.total_amount.min' => 'Total amount cannot be negative',
            ],
            'document' => [
                'title.required' => 'Document title is required',
                'summary.min' => 'Summary must be at least 3 characters long',
            ],
        ][$type] ?? [];
    }

    protected function getAppliedRules(string $type): array
    {
        return array_keys($this->validationRules[$type] ?? []);
    }

    protected function getAppliedTransformations(array $original, array $sanitized): array
    {
        $transformations = [];

        // Compare original and sanitized to identify transformations
        // This is a simplified implementation
        if (json_encode($original) !== json_encode($sanitized)) {
            $transformations[] = 'data_sanitization';
        }

        return $transformations;
    }

    // Utility methods
    protected function validateJsonSchema(array $data, array $schema): array
    {
        // Simplified JSON schema validation
        // In production, you might want to use a proper JSON schema validator library
        $errors = [];

        if (isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! isset($data[$required])) {
                    $errors[] = "Missing required field: {$required}";
                }
            }
        }

        return $errors;
    }

    protected function hasNonEmptyValue(array $data, string $field): bool
    {
        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (! isset($value[$key])) {
                return false;
            }
            $value = $value[$key];
        }

        return ! empty($value) || is_numeric($value);
    }

    protected function checkForSuspiciousValues(array $data, array &$issues): void
    {
        // Check for obviously fake or suspicious values
        $this->recursivelyCheckValues($data, $issues, '');
    }

    protected function recursivelyCheckValues($data, array &$issues, string $path): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : $key;
                $this->recursivelyCheckValues($value, $issues, $currentPath);
            }
        } elseif (is_string($data)) {
            // Check for suspicious patterns
            if (preg_match('/test|sample|example|lorem ipsum/i', $data)) {
                $issues[] = [
                    'level' => 'warning',
                    'type' => 'suspicious_test_data',
                    'message' => "Field '{$path}' contains test/sample data",
                    'field' => $path,
                    'value' => $data,
                ];
            }
        }
    }

    protected function isValidDate(string $date): bool
    {
        return (bool) strtotime($date);
    }

    protected function isDateInFuture(string $date): bool
    {
        return strtotime($date) > time();
    }

    protected function isDateTooOld(string $date, int $years): bool
    {
        return strtotime($date) < strtotime("-{$years} years");
    }

    protected function normalizeDate(string $date): string
    {
        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : $date;
    }
}
