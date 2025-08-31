<?php

namespace App\Services\AI;

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $data,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $metadata = []
    ) {}

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

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function getAllIssues(): array
    {
        return array_merge($this->errors, $this->warnings);
    }

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
