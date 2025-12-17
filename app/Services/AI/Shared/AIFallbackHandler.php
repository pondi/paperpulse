<?php

namespace App\Services\AI\Shared;

use Exception;

/**
 * Centralized helpers for AI fallback decisions and standardized results.
 */
class AIFallbackHandler
{
    /**
     * Determine if an exception should trigger a fallback attempt.
     */
    public static function shouldAttemptFallback(Exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'API') ||
               str_contains($message, 'rate limit') ||
               str_contains($message, 'timeout') ||
               str_contains($message, 'Invalid schema') ||
               str_contains($message, 'required');
    }

    /**
     * Get fallback model configuration by provider.
     *
     * @return array{model:string,max_tokens:int,temperature:float}
     */
    public static function getFallbackModel(string $provider): array
    {
        $fallbackModels = [
            'openai' => config('ai.models.fallback'),
            'anthropic' => 'claude-3-haiku-20240307',
        ];

        return [
            'model' => $fallbackModels[$provider] ?? $fallbackModels['openai'],
            'max_tokens' => 1024,
            'temperature' => 0.1,
        ];
    }

    /**
     * Create a relaxed OpenAI payload (json_object) for fallback.
     */
    public static function createOpenAIFallbackPayload(array $messages, ?string $model = null): array
    {
        $config = self::getFallbackModel('openai');

        return [
            'model' => $model ?? $config['model'],
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
        ];
    }

    /**
     * Create a simple Anthropic payload for fallback scenarios.
     */
    public static function createAnthropicFallbackPayload(string $content): array
    {
        $config = self::getFallbackModel('anthropic');

        return [
            'model' => $config['model'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'system' => 'Extract receipt data from the following text. Return JSON with merchant name, total amount, date, and items.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => substr($content, 0, 4000),
                ],
            ],
        ];
    }

    /**
     * Build a standardized error result.
     */
    public static function createErrorResult(string $provider, Exception $e, float $startTime, array $context = []): array
    {
        $processingTime = microtime(true) - $startTime;

        return array_merge([
            'success' => false,
            'error' => $e->getMessage(),
            'provider' => $provider,
            'processing_time_ms' => round($processingTime * 1000, 2),
        ], $context);
    }

    /**
     * Build a standardized success result envelope.
     */
    public static function createSuccessResult(string $provider, array $data, array $context = []): array
    {
        return array_merge([
            'success' => true,
            'data' => $data,
            'provider' => $provider,
        ], $context);
    }
}
