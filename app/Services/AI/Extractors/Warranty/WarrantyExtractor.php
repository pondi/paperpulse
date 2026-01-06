<?php

namespace App\Services\AI\Extractors\Warranty;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Warranty-specific extractor.
 *
 * Extracts structured warranty data using simplified schema.
 */
class WarrantyExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected WarrantyValidator $validator,
        protected WarrantyDataNormalizer $normalizer
    ) {}

    /**
     * Extract warranty data from file URI.
     *
     * @param  string  $fileUri  Gemini Files API URI
     * @param  File  $file  File model
     * @param  array  $context  Context (classification, etc.)
     * @return array Extracted and normalized data
     *
     * @throws Exception
     */
    public function extract(string $fileUri, File $file, array $context = []): array
    {
        $schema = $this->getSchema();
        $prompt = $this->getPrompt();

        Log::info('[WarrantyExtractor] Extracting warranty data', [
            'file_id' => $file->id,
            'file_uri' => $fileUri,
        ]);

        try {
            // Call Gemini for extraction
            $response = $this->provider->analyzeFileByUri(
                $fileUri,
                $schema,
                $prompt,
                [] // No conversation history for now (can add Pass 1 context later)
            );

            $rawData = $response['data'] ?? [];

            Log::debug('[WarrantyExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'provider_name' => $rawData['provider_name'] ?? 'N/A',
                'product_name' => $rawData['product_name'] ?? 'N/A',
                'warranty_end_date' => $rawData['warranty_end_date'] ?? 'N/A',
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Warranty validation failed: '.implode(', ', $validation['errors']);
                Log::error('[WarrantyExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[WarrantyExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[WarrantyExtractor] Extraction complete', [
                'file_id' => $file->id,
                'provider' => $normalized['provider']['name'] ?? 'N/A',
                'product' => $normalized['product']['name'] ?? 'N/A',
                'warranty_end_date' => $normalized['dates']['warranty_end_date'] ?? 'N/A',
            ]);

            return [
                'type' => 'warranty',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[WarrantyExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified warranty schema.
     */
    public function getSchema(): array
    {
        return WarrantySchema::get();
    }

    /**
     * Get warranty extraction prompt.
     */
    public function getPrompt(): string
    {
        return WarrantySchema::getPrompt();
    }
}
