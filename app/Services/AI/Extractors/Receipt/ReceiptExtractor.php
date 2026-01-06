<?php

namespace App\Services\AI\Extractors\Receipt;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Receipt-specific extractor (Pass 2).
 *
 * Extracts structured receipt data using simplified schema.
 */
class ReceiptExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected ReceiptValidator $validator,
        protected ReceiptDataNormalizer $normalizer
    ) {}

    /**
     * Extract receipt data from file URI.
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

        Log::info('[ReceiptExtractor] Extracting receipt data', [
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

            Log::debug('[ReceiptExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'merchant_name' => $rawData['merchant_name'] ?? 'N/A',
                'total_amount' => $rawData['total_amount'] ?? 'N/A',
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Receipt validation failed: '.implode(', ', $validation['errors']);
                Log::error('[ReceiptExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[ReceiptExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[ReceiptExtractor] Extraction complete', [
                'file_id' => $file->id,
                'merchant' => $normalized['merchant']['name'] ?? 'N/A',
                'items_count' => count($normalized['items'] ?? []),
                'total' => $normalized['totals']['total_amount'] ?? 'N/A',
            ]);

            return [
                'type' => 'receipt',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[ReceiptExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified receipt schema.
     */
    public function getSchema(): array
    {
        return ReceiptSchema::get();
    }

    /**
     * Get receipt extraction prompt.
     */
    public function getPrompt(): string
    {
        return ReceiptSchema::getPrompt();
    }
}
