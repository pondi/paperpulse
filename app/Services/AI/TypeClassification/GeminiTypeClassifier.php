<?php

namespace App\Services\AI\TypeClassification;

use App\Exceptions\GeminiApiException;
use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Support\Facades\Log;

/**
 * Gemini-based document type classifier (Pass 1).
 *
 * Uses Gemini Files API to analyze document and determine type.
 */
class GeminiTypeClassifier implements TypeClassifier
{
    public function __construct(
        protected GeminiProvider $provider
    ) {}

    /**
     * Classify a document using Gemini.
     *
     * @param  string  $fileUri  Gemini Files API URI
     * @param  array  $hints  Optional hints (filename, extension, etc.)
     *
     * @throws GeminiApiException
     */
    public function classify(string $fileUri, array $hints = []): ClassificationResult
    {
        $schema = ClassificationSchema::get();
        $prompt = ClassificationSchema::getPrompt($hints);

        Log::info('[GeminiTypeClassifier] Classifying document', [
            'file_uri' => $fileUri,
            'hints' => $hints,
        ]);

        try {
            // Call Gemini with classification schema
            $response = $this->provider->analyzeFileByUri(
                $fileUri,
                $schema,
                $prompt,
                [] // No conversation history for Pass 1
            );

            $data = $response['data'] ?? [];

            Log::info('[GeminiTypeClassifier] Classification complete', [
                'type' => $data['document_type'] ?? 'unknown',
                'confidence' => $data['confidence'] ?? 0.0,
            ]);

            return ClassificationResult::fromGeminiResponse($data);

        } catch (GeminiApiException $e) {
            Log::error('[GeminiTypeClassifier] Classification failed', [
                'file_uri' => $fileUri,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
