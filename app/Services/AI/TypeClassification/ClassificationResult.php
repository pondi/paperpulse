<?php

namespace App\Services\AI\TypeClassification;

/**
 * Data Transfer Object for document classification results.
 */
class ClassificationResult
{
    public function __construct(
        public readonly string $type,
        public readonly float $confidence,
        public readonly string $reasoning,
        public readonly ?array $rawData = null
    ) {}

    /**
     * Create from Gemini API response.
     *
     * @param  array  $response  Gemini response data
     * @return static
     */
    public static function fromGeminiResponse(array $response): self
    {
        return new self(
            type: $response['document_type'] ?? 'unknown',
            confidence: (float) ($response['confidence'] ?? 0.0),
            reasoning: $response['reasoning'] ?? 'No reasoning provided',
            rawData: $response
        );
    }

    /**
     * Check if classification is valid for processing.
     *
     * Valid if confidence >= threshold and type is not 'unknown'.
     *
     * @param  float  $threshold  Minimum confidence threshold (default: 0.7)
     */
    public function isValid(float $threshold = 0.7): bool
    {
        return $this->confidence >= $threshold && $this->type !== 'unknown';
    }

    /**
     * Check if this is a high-confidence classification.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.9;
    }

    /**
     * Check if this is a low-confidence classification.
     */
    public function isLowConfidence(): bool
    {
        return $this->confidence < 0.5;
    }

    /**
     * Get confidence level as string.
     *
     * @return string 'very_high', 'high', 'moderate', 'low', 'very_low'
     */
    public function getConfidenceLevel(): string
    {
        return match (true) {
            $this->confidence >= 0.95 => 'very_high',
            $this->confidence >= 0.8 => 'high',
            $this->confidence >= 0.6 => 'moderate',
            $this->confidence >= 0.4 => 'low',
            default => 'very_low',
        };
    }

    /**
     * Convert to array for storage/logging.
     */
    public function toArray(): array
    {
        return [
            'document_type' => $this->type,
            'confidence' => $this->confidence,
            'confidence_level' => $this->getConfidenceLevel(),
            'reasoning' => $this->reasoning,
            'is_valid' => $this->isValid(),
        ];
    }

    /**
     * Get a human-readable description.
     */
    public function getDescription(): string
    {
        $confidencePercent = round($this->confidence * 100);

        return "Classified as '{$this->type}' with {$confidencePercent}% confidence: {$this->reasoning}";
    }
}
