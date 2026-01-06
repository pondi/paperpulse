<?php

namespace App\Services\AI\Extractors\Document;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Document-specific extractor (generic fallback).
 *
 * Extracts structured document data using simplified schema.
 * Used as fallback when document doesn't match specific types (receipt, invoice, etc.).
 */
class DocumentExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected DocumentValidator $validator,
        protected DocumentDataNormalizer $normalizer
    ) {}

    /**
     * Extract document data from file URI.
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

        Log::info('[DocumentExtractor] Extracting document data', [
            'file_id' => $file->id,
            'file_uri' => $fileUri,
            'document_type' => $context['document_type'] ?? 'generic',
        ]);

        try {
            // Call Gemini for extraction
            $response = $this->provider->analyzeFileByUri(
                $fileUri,
                $schema,
                $prompt,
                [] // No conversation history for now (can add context later)
            );

            $rawData = $response['data'] ?? [];

            Log::debug('[DocumentExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'document_title' => $rawData['document_title'] ?? 'N/A',
                'document_type' => $rawData['document_type'] ?? 'N/A',
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Document validation failed: '.implode(', ', $validation['errors']);
                Log::error('[DocumentExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[DocumentExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[DocumentExtractor] Extraction complete', [
                'file_id' => $file->id,
                'title' => $normalized['metadata']['title'] ?? 'N/A',
                'type' => $normalized['metadata']['type'] ?? 'N/A',
                'entities_count' => count($normalized['entities_mentioned'] ?? []),
            ]);

            return [
                'type' => 'document',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[DocumentExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified document schema.
     */
    public function getSchema(): array
    {
        return DocumentSchema::get();
    }

    /**
     * Get document extraction prompt.
     */
    public function getPrompt(): string
    {
        return DocumentSchema::getPrompt();
    }
}
