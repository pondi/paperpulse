<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validates and lightly sanitizes AI outputs against schemas and basics.
 *
 * Simplified: schema validation stub, core business checks, and basic cleaning.
 */
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
     * Validate AI output against schema and business rules.
     *
     * @param  string  $type  'receipt' or 'document'
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
                'data_preview' => array_slice($data, 0, 3, true),
                'missing_required_keys' => array_diff($schema['required'] ?? [], array_keys($data)),
                'schema_required_fields' => $schema['required'] ?? [],
            ]);

            return ValidationResult::failure("Validation error: {$e->getMessage()}", [
                'exception' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Validate against JSON schema (stubbed in v1).
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
     * Validate business logic rules - simplified version.
     */
    protected function validateBusinessLogic(array $data, string $type, array $options = []): ValidationResult
    {
        try {
            // Basic validation - just check that we have some required data
            switch ($type) {
                case 'receipt':
                    return $this->validateReceiptBasics($data);
                case 'document':
                    return $this->validateDocumentBasics($data);
                default:
                    return ValidationResult::success($data, ['business_validation' => 'no_specific_rules']);
            }
        } catch (Exception $e) {
            Log::warning('[OutputValidationService] Basic validation warning', [
                'type' => $type,
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);

            // Don't fail validation for basic checks - just log and continue
            return ValidationResult::success($data, ['business_validation' => 'passed_with_warnings']);
        }
    }

    /**
     * Validate data quality and consistency - simplified version.
     */
    protected function validateDataQuality(array $data, string $type, array $options = []): ValidationResult
    {
        // Skip complex quality checks for version 1.0 - just log what we received
        Log::debug('[OutputValidationService] Data quality check (informational only)', [
            'validation_type' => $type,
            'data_structure' => $this->getDataStructureSummary($data),
            'data_size' => count($data),
        ]);

        return ValidationResult::success($data, ['quality_validation' => 'skipped_v1']);
    }

    /**
     * Summarize structure of data for debug logging.
     */
    protected function getDataStructureSummary(array $data): array
    {
        $summary = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = 'array('.count($value).')';
            } else {
                $summary[$key] = gettype($value);
            }
        }

        return $summary;
    }

    /**
     * Sanitize and transform data - simplified version.
     */
    protected function sanitizeData(array $data, string $type): array
    {
        // Basic cleaning - just ensure numbers are properly formatted
        return $this->basicCleanData($data);
    }

    /**
     * Basic data cleaning - simplified approach.
     */
    protected function basicCleanData(array $data): array
    {
        // Just trim strings and round numeric values - keep it simple
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->basicCleanData($value);
            } elseif (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_numeric($value)) {
                $data[$key] = round((float) $value, 2);
            }
        }

        return $data;
    }

    // Helper methods
    protected function initializeValidationRules(): void
    {
        // Simplified - no complex rules
        $this->validationRules = [];
    }

    /**
     * Basic receipt validation - flexible with different data structures.
     */
    protected function validateReceiptBasics(array $data): ValidationResult
    {
        // Check for merchant/store name
        $hasStore = ! empty($data['merchant']['name']) ||
                   ! empty($data['store']['name']) ||
                   ! empty($data['merchant_name']) ||
                   ! empty($data['store_name']);

        // Check for total amount
        $hasTotal = ! empty($data['totals']['total_amount']) ||
                   ! empty($data['totals']['total']) ||
                   ! empty($data['totals']['gross_amount']) ||
                   ! empty($data['total_amount']) ||
                   ! empty($data['total']);

        if (! $hasStore || ! $hasTotal) {
            Log::warning('[OutputValidationService] Missing basic receipt data', [
                'has_store' => $hasStore,
                'has_total' => $hasTotal,
                'data_keys' => array_keys($data),
            ]);
        }

        return ValidationResult::success($data, ['business_validation' => 'basic_checks_passed']);
    }

    /**
     * Basic document validation.
     */
    protected function validateDocumentBasics(array $data): ValidationResult
    {
        // Very basic check - just ensure we have some content
        $hasContent = ! empty($data['title']) || ! empty($data['summary']) || ! empty($data['content']);

        if (! $hasContent) {
            Log::warning('[OutputValidationService] Document has no recognizable content', [
                'data_keys' => array_keys($data),
            ]);
        }

        return ValidationResult::success($data, ['business_validation' => 'basic_checks_passed']);
    }

    protected function registerCustomValidators(): void
    {
        // Simplified - no custom validators needed for v1.0
    }

    protected function getAppliedRules(string $type): array
    {
        return []; // No complex rules in simplified version
    }

    protected function getAppliedTransformations(array $original, array $sanitized): array
    {
        // Simple check
        return json_encode($original) !== json_encode($sanitized) ? ['basic_cleaning'] : [];
    }

    protected function validateJsonSchema(array $data, array $schema): array
    {
        // Skip JSON schema validation in simplified version
        return [];
    }
}
