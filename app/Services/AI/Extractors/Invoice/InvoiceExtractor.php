<?php

namespace App\Services\AI\Extractors\Invoice;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Invoice-specific extractor (Pass 2).
 *
 * Extracts structured invoice data using simplified schema.
 */
class InvoiceExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected InvoiceValidator $validator,
        protected InvoiceDataNormalizer $normalizer
    ) {}

    /**
     * Extract invoice data from file URI.
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

        Log::info('[InvoiceExtractor] Extracting invoice data', [
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

            Log::debug('[InvoiceExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'vendor_name' => $rawData['vendor_name'] ?? 'N/A',
                'invoice_number' => $rawData['invoice_number'] ?? 'N/A',
                'total_amount' => $rawData['total_amount'] ?? 'N/A',
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Invoice validation failed: '.implode(', ', $validation['errors']);
                Log::error('[InvoiceExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[InvoiceExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[InvoiceExtractor] Extraction complete', [
                'file_id' => $file->id,
                'vendor' => $normalized['vendor']['name'] ?? 'N/A',
                'invoice_number' => $normalized['invoice_info']['invoice_number'] ?? 'N/A',
                'line_items_count' => count($normalized['line_items'] ?? []),
                'total' => $normalized['totals']['total_amount'] ?? 'N/A',
            ]);

            return [
                'type' => 'invoice',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[InvoiceExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified invoice schema.
     */
    public function getSchema(): array
    {
        return InvoiceSchema::get();
    }

    /**
     * Get invoice extraction prompt.
     */
    public function getPrompt(): string
    {
        return InvoiceSchema::getPrompt();
    }
}
