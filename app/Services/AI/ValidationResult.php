<?php

namespace App\Services\AI;

/**
 * Immutable result object for AI output validation.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $data,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $metadata = []
    ) {}

    /**
     * Build a successful validation result.
     */
    public static function success(array $data, array $metadata = []): self
    {
        return new self(
            isValid: true,
            data: $data,
            errors: [],
            warnings: [],
            metadata: $metadata
        );
    }

    /**
     * Build a failed validation result with a single error.
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            isValid: false,
            data: [],
            errors: [$error],
            warnings: [],
            metadata: $metadata
        );
    }

    /**
     * Build a successful validation with warnings.
     */
    public static function withWarnings(array $data, array $warnings, array $metadata = []): self
    {
        return new self(
            isValid: true,
            data: $data,
            errors: [],
            warnings: $warnings,
            metadata: $metadata
        );
    }

    /** Check if any warnings were recorded. */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /** Get the first error message, if any. */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /** Merge errors and warnings for a flat list of issues. */
    public function getAllIssues(): array
    {
        return array_merge($this->errors, $this->warnings);
    }

    /** Serialize the result for logging or API responses. */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'data' => $this->data,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }
}
