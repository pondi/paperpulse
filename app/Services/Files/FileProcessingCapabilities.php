<?php

namespace App\Services\Files;

use InvalidArgumentException;

class FileProcessingCapabilities
{
    public function __construct(public readonly string $provider, public readonly bool $ocrFallback = false)
    {
        if (! in_array($provider, ['gemini', 'textract+openai', 'ocr-only'], true)) {
            throw new InvalidArgumentException('Unsupported file processing provider: '.$provider);
        }
    }

    public static function fromMetadata(array $metadata): self
    {
        return new self($metadata['processingProvider'] ?? config('ai.file_processing_provider', 'textract+openai'), $metadata['ocrFallback'] ?? false);
    }

    public function requiresOcr(): bool
    {
        return $this->provider !== 'gemini' || $this->ocrFallback;
    }

    public function supportsNativeExtension(string $extension): bool
    {
        return $this->provider === 'gemini' && in_array(strtolower($extension), ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'md'], true);
    }
}
