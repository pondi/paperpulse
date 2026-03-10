<?php

declare(strict_types=1);

namespace App\Services\Factories\Concerns;

/**
 * Normalizes AI-returned values that may contain stringified nulls
 * (e.g. "null", "None", "N/A") which would cause Carbon parsing failures
 * when passed to Eloquent date casts.
 */
trait SanitizesAiData
{
    /**
     * Sanitize date fields in the data array, converting string representations
     * of null to actual null values.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $dateFields
     * @return array<string, mixed>
     */
    protected function sanitizeDates(array $data, array $dateFields): array
    {
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullIfEmpty($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Convert stringified null/empty values to actual null.
     * Handles common AI responses like "null", "none", "N/A", "n/a", "".
     */
    protected function nullIfEmpty(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['null', 'none', 'n/a', 'na', 'nil', 'undefined', ''], true)) {
            return null;
        }

        return $value;
    }
}
