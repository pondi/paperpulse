<?php

namespace App\Services\AI;

use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Log;

class AIServiceFactory
{
    private static array $instances = [];

    private static ?ModelConfigurationService $modelService = null;

    private static ?ResilienceService $resilienceService = null;

    /**
     * Create an AI service instance with resilience and fallback
     */
    public static function create(?string $provider = null, array $requirements = []): AIService
    {
        // If no specific provider, try with failover
        if (! $provider) {
            $task = $requirements['task'] ?? 'general';
            try {
                return self::createWithFailover($task, $requirements);
            } catch (\Exception $e) {
                Log::error('Failover creation failed, using basic method', [
                    'task' => $task,
                    'error' => $e->getMessage(),
                ]);

                // Fall back to basic creation
                return self::createBasic(null, $requirements);
            }
        }

        // Specific provider requested
        return self::createBasic($provider, $requirements);
    }

    /**
     * Create service for specific task with optimization
     */
    public static function createForTask(string $task, array $options = []): AIService
    {
        $requirements = array_merge([
            'task' => $task,
            'quality' => $options['quality'] ?? 'high',
            'budget' => $options['budget'] ?? 'standard',
            'priority' => $options['priority'] ?? 'balanced',
        ], $options);

        return self::create(null, $requirements);
    }

    /**
     * Create with fallback and model optimization
     */
    public static function createWithFallback(array $providers = []): AIService
    {
        $providers = empty($providers) ? ['openai', 'anthropic'] : $providers;

        foreach ($providers as $provider) {
            try {
                return self::create($provider);
            } catch (\Exception $e) {
                Log::warning("Failed to create AI provider: {$provider}", [
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new \RuntimeException('All AI providers failed to initialize');
    }

    /**
     * Get model configuration service
     */
    protected static function getModelService(): ModelConfigurationService
    {
        if (self::$modelService === null) {
            self::$modelService = app(ModelConfigurationService::class);
        }

        return self::$modelService;
    }

    /**
     * Get all available models
     */
    public static function getAvailableModels(?string $provider = null): array
    {
        return self::getModelService()->getAvailableModels($provider);
    }

    /**
     * Get optimal model for task without creating service
     */
    public static function getOptimalModelForTask(string $task, array $requirements = []): ModelConfiguration
    {
        return self::getModelService()->getOptimalModel($task, $requirements);
    }

    /**
     * Update model performance metrics
     */
    public static function updateModelPerformance(string $modelName, string $task, array $metrics): void
    {
        self::getModelService()->updatePerformanceMetrics($modelName, $task, $metrics);
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
     */
    public static function getAvailableProviders(): array
    {
        return ['openai', 'anthropic'];
    }

    /**
     * Check if a provider is available
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
     */
    public static function getDefaultProvider(): string
    {
        return config('ai.provider', 'openai');
    }

    /**
     * Create with automatic provider failover
     */
    public static function createWithFailover(string $task, array $options = []): AIService
    {
        $providers = config('ai.fallback_chains.'.$task, ['openai', 'anthropic']);
        $lastException = null;

        foreach ($providers as $provider) {
            try {
                return self::createBasic($provider, array_merge($options, ['task' => $task]));
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Provider {$provider} failed, trying next", [
                    'provider' => $provider,
                    'task' => $task,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new \RuntimeException(
            "All providers failed for task: {$task}. Last error: ".
            ($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Create basic provider without resilience wrapper
     */
    public static function createBasic(?string $provider = null, array $requirements = []): AIService
    {
        $modelService = self::getModelService();

        // Get optimal model configuration
        $task = $requirements['task'] ?? 'general';
        $modelConfig = $modelService->getOptimalModel($task, $requirements);
        $provider = $provider ?? $modelConfig->provider;
        $instanceKey = $provider.'_'.$modelConfig->name.'_basic';

        // Return cached instance if available
        if (isset(self::$instances[$instanceKey])) {
            return self::$instances[$instanceKey];
        }

        Log::info('Creating basic AI provider instance', [
            'provider' => $provider,
            'model' => $modelConfig->name,
            'task' => $task,
        ]);

        // Create base provider
        $baseProvider = match ($provider) {
            'openai' => new OpenAIProvider($modelConfig),
            'anthropic' => new AnthropicProvider($modelConfig),
            default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}")
        };

        // Wrap with validation only
        $validatedProvider = new ValidatedAIService($baseProvider, $modelConfig);

        self::$instances[$instanceKey] = $validatedProvider;

        return $validatedProvider;
    }

    /**
     * Create with budget optimization and failover
     */
    public static function createOptimized(string $task, array $requirements = []): AIService
    {
        try {
            // First try optimal selection
            return self::create(null, array_merge($requirements, ['task' => $task]));

        } catch (\Exception $e) {
            Log::warning('Optimal provider creation failed, using fallback', [
                'task' => $task,
                'error' => $e->getMessage(),
            ]);

            // Fallback to basic requirements
            $basicRequirements = [
                'task' => $task,
                'budget' => 'economy',
                'quality' => 'basic',
                'provider' => 'any',
            ];

            return self::createWithFailover($task, $basicRequirements);
        }
    }

    /**
     * Get resilience service
     */
    protected static function getResilienceService(): ResilienceService
    {
        if (self::$resilienceService === null) {
            self::$resilienceService = app(ResilienceService::class);
        }

        return self::$resilienceService;
    }

    /**
     * Get system health status
     */
    public static function getSystemHealth(): array
    {
        return self::getResilienceService()->getSystemHealth();
    }

    /**
     * Reset circuit breakers for all providers
     */
    public static function resetCircuitBreakers(): void
    {
        $providers = ['openai', 'anthropic'];
        $resilienceService = self::getResilienceService();

        foreach ($providers as $provider) {
            $resilienceService->resetCircuitBreaker($provider);
        }

        Log::info('[AIServiceFactory] All circuit breakers reset');
    }
}
