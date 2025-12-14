<?php

namespace App\Services\AI\OpenAI;

/**
 * Builds OpenAI Chat Completions payloads for structured responses.
 */
class ChatPayloadBuilder
{
    /**
     * Build payload for receipt extraction with JSON Schema.
     *
     * @param  array  $promptData  Output of PromptTemplateService::getPrompt
     * @param  string  $model  Model name
     * @param  array  $params  Overrides like max_tokens/temperature
     */
    public static function forReceipt(array $promptData, string $model, array $params): array
    {
        return array_merge([
            'model' => $model,
            'messages' => $promptData['messages'],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'receipt_analysis',
                    'description' => 'Structured receipt data extraction',
                    'schema' => $promptData['schema'],
                    'strict' => (bool) config('ai.options.strict_json_schema', false),
                ],
            ],
        ], $params);
    }

    /**
     * Build payload for document analysis with JSON Schema.
     */
    public static function forDocument(array $promptData, string $model): array
    {
        return [
            'model' => $model,
            'messages' => $promptData['messages'],
            'temperature' => $promptData['options']['temperature'] ?? 0.2,
            'max_tokens' => $promptData['options']['max_tokens'] ?? 3000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'document_analysis',
                    'description' => 'Structured document metadata extraction',
                    'schema' => $promptData['schema'],
                    'strict' => (bool) config('ai.options.strict_json_schema', false),
                ],
            ],
        ];
    }
}
