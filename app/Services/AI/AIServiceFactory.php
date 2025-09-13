<?php

namespace App\Services\AI;

use App\Services\AI\Providers\OpenAIProvider;

/**
 * Factory for creating configured AIService implementations.
 *
 * Currently supports OpenAI and caches a single lightweight instance.
 */
class AIServiceFactory
{
    private static array $instances = [];

    /**
     * Clear cached instances (useful in tests/config changes).
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }

    /**
     * Create an AI service (OpenAI only in simplified core).
     *
     * @param string|null $provider Provider name; defaults to config
     * @param array $requirements   Reserved for future capability hints
     * @return AIService
     */
    public static function create(?string $provider = null, array $requirements = []): AIService
    {
        $provider = $provider ?? config('ai.provider', 'openai');

        if ($provider !== 'openai') {
            throw new \InvalidArgumentException("Unsupported AI provider: {$provider}. Only 'openai' is supported in the simplified core.");
        }

        $instanceKey = 'openai_core';
        if (isset(self::$instances[$instanceKey])) {
            return self::$instances[$instanceKey];
        }

        // No model-selection layer: provider handles model choice via config('ai.models.*')
        $service = new OpenAIProvider(null);
        self::$instances[$instanceKey] = $service;

        return $service;
    }
}
