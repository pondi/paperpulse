<?php

namespace App\Services\AI\OpenAI;

class ChatPayloadBuilder
{
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
                    'strict' => true,
                ],
            ],
        ];
    }
}

