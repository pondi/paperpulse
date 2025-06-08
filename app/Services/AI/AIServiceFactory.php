<?php

namespace App\Services\AI;

use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Log;

class AIServiceFactory
{
    private static array $instances = [];

    /**
     * Create an AI service instance based on configuration
     *
     * @param string|null $provider Override the default provider
     * @return AIService
     * @throws \InvalidArgumentException
     */
    public static function create(?string $provider = null): AIService
    {
        $provider = $provider ?? config('ai.provider', 'openai');

        // Return cached instance if available
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        Log::info('Creating AI provider instance', ['provider' => $provider]);

        $instance = match ($provider) {
            'openai' => new OpenAIProvider(),
            'anthropic' => new AnthropicProvider(),
            default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}")
        };

        // Cache the instance
        self::$instances[$provider] = $instance;

        return $instance;
    }

    /**
     * Create with fallback support
     *
     * @param array $providers Priority list of providers to try
     * @return AIService
     */
    public static function createWithFallback(array $providers = []): AIService
    {
        $providers = empty($providers) ? ['openai', 'anthropic'] : $providers;
        
        foreach ($providers as $provider) {
            try {
                return self::create($provider);
            } catch (\Exception $e) {
                Log::warning("Failed to create AI provider: {$provider}", [
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        throw new \RuntimeException('All AI providers failed to initialize');
    }

    /**
     * Clear cached instances
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }

    /**
     * Get all available providers
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        return ['openai', 'anthropic'];
    }

    /**
     * Check if a provider is available
     *
     * @param string $provider
     * @return bool
     */
    public static function isProviderAvailable(string $provider): bool
    {
        try {
            $service = self::create($provider);
            return $service instanceof AIService;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the default provider from configuration
     *
     * @return string
     */
    public static function getDefaultProvider(): string
    {
        return config('ai.provider', 'openai');
    }
}