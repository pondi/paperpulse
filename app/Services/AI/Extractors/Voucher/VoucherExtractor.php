<?php

namespace App\Services\AI\Extractors\Voucher;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Voucher-specific extractor.
 *
 * Extracts structured voucher data using simplified schema.
 */
class VoucherExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected VoucherValidator $validator,
        protected VoucherDataNormalizer $normalizer
    ) {}

    /**
     * Extract voucher data from file URI.
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

        Log::info('[VoucherExtractor] Extracting voucher data', [
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

            Log::debug('[VoucherExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'issuer_name' => $rawData['issuer_name'] ?? 'N/A',
                'voucher_code' => $rawData['voucher_code'] ?? 'N/A',
                'value_amount' => $rawData['value_amount'] ?? 'N/A',
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Voucher validation failed: '.implode(', ', $validation['errors']);
                Log::error('[VoucherExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[VoucherExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[VoucherExtractor] Extraction complete', [
                'file_id' => $file->id,
                'issuer' => $normalized['issuer']['name'] ?? 'N/A',
                'code' => $normalized['voucher']['code'] ?? 'N/A',
                'value' => $normalized['value']['amount'] ?? 'N/A',
            ]);

            return [
                'type' => 'voucher',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[VoucherExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified voucher schema.
     */
    public function getSchema(): array
    {
        return VoucherSchema::get();
    }

    /**
     * Get voucher extraction prompt.
     */
    public function getPrompt(): string
    {
        return VoucherSchema::getPrompt();
    }
}
