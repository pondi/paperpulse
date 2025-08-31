<?php

namespace App\Services\OCR;

class OCRResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $text,
        public readonly array $metadata = [],
        public readonly ?string $error = null,
        public readonly string $provider = '',
        public readonly float $confidence = 0.0,
        public readonly array $pages = [],
        public readonly array $blocks = [],
        public readonly int $processingTime = 0
    ) {}

    /**
     * Create a successful result
     */
    public static function success(
        string $text,
        string $provider,
        array $metadata = [],
        float $confidence = 1.0,
        array $pages = [],
        array $blocks = [],
        int $processingTime = 0
    ): self {
        return new self(
            success: true,
            text: $text,
            metadata: $metadata,
            error: null,
            provider: $provider,
            confidence: $confidence,
            pages: $pages,
            blocks: $blocks,
            processingTime: $processingTime
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(string $error, string $provider): self
    {
        return new self(
            success: false,
            text: '',
            metadata: [],
            error: $error,
            provider: $provider
        );
    }

    /**
     * Get text with confidence filtering
     */
    public function getFilteredText(float $minConfidence = 0.8): string
    {
        if ($this->confidence < $minConfidence) {
            return '';
        }

        return $this->text;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'text' => $this->text,
            'metadata' => $this->metadata,
            'error' => $this->error,
            'provider' => $this->provider,
            'confidence' => $this->confidence,
            'pages' => $this->pages,
            'blocks' => $this->blocks,
            'processing_time' => $this->processingTime,
        ];
    }
}
