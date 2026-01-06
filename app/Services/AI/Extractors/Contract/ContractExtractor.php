<?php

namespace App\Services\AI\Extractors\Contract;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Contract-specific extractor.
 *
 * Extracts structured contract data using simplified schema.
 */
class ContractExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected ContractValidator $validator,
        protected ContractDataNormalizer $normalizer
    ) {}

    /**
     * Extract contract data from file URI.
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

        Log::info('[ContractExtractor] Extracting contract data', [
            'file_id' => $file->id,
            'file_uri' => $fileUri,
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

            Log::debug('[ContractExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'contract_title' => $rawData['contract_title'] ?? 'N/A',
                'contract_type' => $rawData['contract_type'] ?? 'N/A',
                'parties_count' => count($rawData['parties'] ?? []),
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Contract validation failed: '.implode(', ', $validation['errors']);
                Log::error('[ContractExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[ContractExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[ContractExtractor] Extraction complete', [
                'file_id' => $file->id,
                'contract_title' => $normalized['contract_title'] ?? 'N/A',
                'parties_count' => count($normalized['parties'] ?? []),
                'contract_value' => $normalized['financial']['contract_value'] ?? 'N/A',
            ]);

            return [
                'type' => 'contract',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[ContractExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified contract schema.
     */
    public function getSchema(): array
    {
        return ContractSchema::get();
    }

    /**
     * Get contract extraction prompt.
     */
    public function getPrompt(): string
    {
        return ContractSchema::getPrompt();
    }
}
