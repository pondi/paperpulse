<?php

namespace App\Services\AI\Extractors;

use App\Models\File;

/**
 * Contract for type-specific entity extractors.
 */
interface EntityExtractorContract
{
    /**
     * Extract structured data from file URI.
     *
     * @param  string  $fileUri  Gemini Files API URI
     * @param  File  $file  File model being processed
     * @param  array  $context  Additional context (classification result, etc.)
     * @return array Extracted data ready for EntityFactory
     */
    public function extract(string $fileUri, File $file, array $context = []): array;

    /**
     * Get simplified schema for this entity type.
     *
     * @return array Schema configuration with responseSchema key
     */
    public function getSchema(): array;

    /**
     * Get extraction prompt for this entity type.
     *
     * @return string Prompt text
     */
    public function getPrompt(): string;
}
