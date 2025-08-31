<?php

namespace App\Services\AI\Shared;

class AIFallbackHandler
{
    /**
     * Check if error should trigger fallback attempt
     */
    public static function shouldAttemptFallback(\Exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'API') ||
               str_contains($message, 'rate limit') ||
               str_contains($message, 'timeout') ||
               str_contains($message, 'Invalid schema') ||
               str_contains($message, 'required');
    }

    /**
     * Get fallback model configuration
     */
    public static function getFallbackModel(string $provider): array
    {
        $fallbackModels = [
            'openai' => 'gpt-4.1-mini',
            'anthropic' => 'claude-3-haiku-20240307',
        ];

        return [
            'model' => $fallbackModels[$provider] ?? $fallbackModels['openai'],
            'max_tokens' => 1024,
            'temperature' => 0.1,
        ];
    }

    /**
     * Create simple fallback payload for OpenAI
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
     * Create simple fallback payload for Anthropic
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
     * Create standardized error result
     */
    public static function createErrorResult(string $provider, \Exception $e, float $startTime, array $context = []): array
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
     * Create standardized success result
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
