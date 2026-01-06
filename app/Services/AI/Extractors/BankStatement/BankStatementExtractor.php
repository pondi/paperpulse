<?php

namespace App\Services\AI\Extractors\BankStatement;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Bank statement-specific extractor.
 *
 * Extracts structured bank statement data using simplified schema.
 */
class BankStatementExtractor implements EntityExtractorContract
{
    public function __construct(
        protected GeminiProvider $provider,
        protected BankStatementValidator $validator,
        protected BankStatementDataNormalizer $normalizer
    ) {}

    /**
     * Extract bank statement data from file URI.
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

        Log::info('[BankStatementExtractor] Extracting bank statement data', [
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

            Log::debug('[BankStatementExtractor] Raw extraction data', [
                'file_id' => $file->id,
                'data_keys' => array_keys($rawData),
                'bank_name' => $rawData['bank_name'] ?? 'N/A',
                'account_number' => $rawData['account_number'] ?? 'N/A',
                'transactions_count' => count($rawData['transactions'] ?? []),
            ]);

            // Validate
            $validation = $this->validator->validate($rawData);

            if (! $validation['valid']) {
                $errorMsg = 'Bank statement validation failed: '.implode(', ', $validation['errors']);
                Log::error('[BankStatementExtractor] Validation failed', [
                    'file_id' => $file->id,
                    'errors' => $validation['errors'],
                ]);
                throw new Exception($errorMsg);
            }

            if (! empty($validation['warnings'])) {
                Log::warning('[BankStatementExtractor] Validation warnings', [
                    'file_id' => $file->id,
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Normalize to EntityFactory format
            $normalized = $this->normalizer->normalize($rawData);

            Log::info('[BankStatementExtractor] Extraction complete', [
                'file_id' => $file->id,
                'bank' => $normalized['bank']['name'] ?? 'N/A',
                'account_number' => $normalized['bank']['account_number'] ?? 'N/A',
                'transactions_count' => count($normalized['transactions'] ?? []),
                'closing_balance' => $normalized['balances']['closing_balance'] ?? 'N/A',
            ]);

            return [
                'type' => 'bank_statement',
                'confidence_score' => $rawData['confidence_score'] ?? 0.85,
                'data' => $normalized,
                'validation_warnings' => $validation['warnings'] ?? [],
            ];

        } catch (GeminiApiException $e) {
            Log::error('[BankStatementExtractor] Gemini extraction failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get simplified bank statement schema.
     */
    public function getSchema(): array
    {
        return BankStatementSchema::get();
    }

    /**
     * Get bank statement extraction prompt.
     */
    public function getPrompt(): string
    {
        return BankStatementSchema::getPrompt();
    }
}
