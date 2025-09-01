<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResilienceService
{
    protected array $circuitBreakers = [];

    protected array $fallbackChains = [];

    protected array $healthChecks = [];

    public function __construct()
    {
        $this->initializeFallbackChains();
        $this->initializeHealthChecks();
    }

    /**
     * Execute operation with full resilience pattern
     */
    public function executeWithResilience(
        callable $operation,
        string $operationType,
        array $options = []
    ): mixed {
        $providers = $this->getFallbackChain($operationType, $options);
        $lastException = null;

        foreach ($providers as $providerName) {
            try {
                // Check circuit breaker
                if ($this->isCircuitBreakerOpen($providerName)) {
                    Log::debug('[ResilienceService] Circuit breaker open, skipping provider', [
                        'provider' => $providerName,
                        'operation' => $operationType,
                    ]);

                    continue;
                }

                // Check provider health
                if (! $this->isProviderHealthy($providerName)) {
                    Log::debug('[ResilienceService] Provider unhealthy, skipping', [
                        'provider' => $providerName,
                        'operation' => $operationType,
                    ]);

                    continue;
                }

                // Execute operation with this provider
                $result = $this->executeWithProvider($operation, $providerName, $options);

                // Record success
                $this->recordSuccess($providerName, $operationType);

                Log::info('[ResilienceService] Operation succeeded', [
                    'provider' => $providerName,
                    'operation' => $operationType,
                    'attempt' => array_search($providerName, $providers) + 1,
                ]);

                return $result;

            } catch (Throwable $e) {
                $lastException = $e;

                // Record failure
                $this->recordFailure($providerName, $operationType, $e);

                Log::warning('[ResilienceService] Provider failed, trying fallback', [
                    'provider' => $providerName,
                    'operation' => $operationType,
                    'error' => $e->getMessage(),
                    'next_provider' => $providers[array_search($providerName, $providers) + 1] ?? 'none',
                ]);

                continue;
            }
        }

        // All providers failed
        Log::error('[ResilienceService] All providers failed', [
            'operation' => $operationType,
            'providers_tried' => $providers,
            'final_error' => $lastException?->getMessage(),
        ]);

        throw new Exception(
            "All providers failed for operation: {$operationType}. ".
            'Last error: '.($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Execute operation with specific provider
     */
    protected function executeWithProvider(callable $operation, string $provider, array $options): mixed
    {
        // Execute operation without passing provider as parameter
        return $operation();
    }

    /**
     * Check if circuit breaker is open for provider
     */
    protected function isCircuitBreakerOpen(string $provider): bool
    {
        $key = "circuit_breaker.{$provider}";
        $state = Cache::get($key);

        if (! $state) {
            return false; // Circuit breaker is closed
        }

        // Check if circuit breaker should be reset
        if (time() > $state['opened_at'] + $state['timeout']) {
            Cache::forget($key);

            return false;
        }

        return true;
    }

    /**
     * Record successful operation
     */
    protected function recordSuccess(string $provider, string $operationType): void
    {
        // Reset circuit breaker on success
        Cache::forget("circuit_breaker.{$provider}");

        // Update success metrics
        $metricsKey = "metrics.{$provider}.{$operationType}";
        $metrics = Cache::get($metricsKey, ['success' => 0, 'failure' => 0, 'total' => 0]);
        $metrics['success']++;
        $metrics['total']++;

        Cache::put($metricsKey, $metrics, now()->addDays(7));
    }

    /**
     * Record failed operation
     */
    protected function recordFailure(string $provider, string $operationType, Throwable $exception): void
    {
        // Update failure metrics
        $metricsKey = "metrics.{$provider}.{$operationType}";
        $metrics = Cache::get($metricsKey, ['success' => 0, 'failure' => 0, 'total' => 0]);
        $metrics['failure']++;
        $metrics['total']++;

        Cache::put($metricsKey, $metrics, now()->addDays(7));

        // Check if we should open circuit breaker
        $failureRate = $metrics['total'] > 10 ? $metrics['failure'] / $metrics['total'] : 0;
        $recentFailures = $this->getRecentFailureCount($provider, 300); // 5 minutes

        if ($failureRate > 0.5 || $recentFailures >= 5) {
            $this->openCircuitBreaker($provider, $exception);
        }
    }

    /**
     * Open circuit breaker for provider
     */
    protected function openCircuitBreaker(string $provider, Throwable $exception): void
    {
        $timeout = $this->getCircuitBreakerTimeout($provider);

        Cache::put("circuit_breaker.{$provider}", [
            'opened_at' => time(),
            'timeout' => $timeout,
            'reason' => $exception->getMessage(),
        ], now()->addSeconds($timeout + 60));

        Log::warning('[ResilienceService] Circuit breaker opened', [
            'provider' => $provider,
            'timeout' => $timeout,
            'reason' => $exception->getMessage(),
        ]);
    }

    /**
     * Get recent failure count for provider
     */
    protected function getRecentFailureCount(string $provider, int $seconds): int
    {
        $key = "recent_failures.{$provider}";
        $failures = Cache::get($key, []);
        $threshold = time() - $seconds;

        return count(array_filter($failures, fn ($timestamp) => $timestamp > $threshold));
    }

    /**
     * Check provider health
     */
    protected function isProviderHealthy(string $provider): bool
    {
        $healthCheck = $this->healthChecks[$provider] ?? null;

        if (! $healthCheck) {
            return true; // No health check defined, assume healthy
        }

        $cacheKey = "health_check.{$provider}";
        $lastCheck = Cache::get($cacheKey);

        // Use cached result if recent
        if ($lastCheck && $lastCheck['timestamp'] > time() - 60) { // 1 minute cache
            return $lastCheck['healthy'];
        }

        // Perform health check
        try {
            $healthy = $healthCheck();

            Cache::put($cacheKey, [
                'healthy' => $healthy,
                'timestamp' => time(),
            ], now()->addMinutes(5));

            return $healthy;

        } catch (Exception $e) {
            Log::warning('[ResilienceService] Health check failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get fallback chain for operation type
     */
    protected function getFallbackChain(string $operationType, array $options): array
    {
        $preferredProvider = $options['provider'] ?? null;
        $defaultChain = $this->fallbackChains[$operationType] ?? ['openai', 'anthropic'];

        if ($preferredProvider) {
            // Put preferred provider first, remove duplicates
            $chain = array_unique(array_merge([$preferredProvider], $defaultChain));
        } else {
            $chain = $defaultChain;
        }

        // Filter out disabled providers
        return array_filter($chain, fn ($provider) => $this->isProviderEnabled($provider));
    }

    /**
     * Initialize fallback chains for different operations
     */
    protected function initializeFallbackChains(): void
    {
        $this->fallbackChains = [
            'receipt' => config('ai.fallback_chains.receipt', ['openai', 'anthropic']),
            'document' => config('ai.fallback_chains.document', ['anthropic', 'openai']),
            'summary' => config('ai.fallback_chains.summary', ['openai', 'anthropic']),
            'classification' => config('ai.fallback_chains.classification', ['openai', 'anthropic']),
            'entities' => config('ai.fallback_chains.entities', ['anthropic', 'openai']),
            'tags' => config('ai.fallback_chains.tags', ['openai', 'anthropic']),
        ];
    }

    /**
     * Initialize health check functions
     */
    protected function initializeHealthChecks(): void
    {
        $this->healthChecks = [
            'openai' => function () {
                try {
                    // Simple check - if API key is configured, assume healthy
                    $apiKey = config('openai.api_key') ?: config('services.openai.api_key');

                    return ! empty($apiKey) && str_starts_with($apiKey, 'sk-');
                } catch (Exception $e) {
                    return false;
                }
            },

            'anthropic' => function () {
                try {
                    // Simple check - if API key is configured, assume healthy
                    $apiKey = config('services.anthropic.api_key');

                    return ! empty($apiKey) && str_starts_with($apiKey, 'sk-ant-');
                } catch (Exception $e) {
                    return false;
                }
            },
        ];
    }

    /**
     * Get circuit breaker timeout for provider
     */
    protected function getCircuitBreakerTimeout(string $provider): int
    {
        $timeouts = [
            'openai' => config('ai.circuit_breaker.openai_timeout', 300),     // 5 minutes
            'anthropic' => config('ai.circuit_breaker.anthropic_timeout', 300), // 5 minutes
        ];

        return $timeouts[$provider] ?? 300;
    }

    /**
     * Check if provider is enabled
     */
    protected function isProviderEnabled(string $provider): bool
    {
        return config("ai.providers.{$provider}.enabled", true);
    }

    /**
     * Get provider metrics
     */
    public function getProviderMetrics(string $provider, int $hours = 24): array
    {
        $metrics = [];

        foreach (['receipt', 'document', 'summary', 'classification'] as $operation) {
            $key = "metrics.{$provider}.{$operation}";
            $data = Cache::get($key, ['success' => 0, 'failure' => 0, 'total' => 0]);
            $metrics[$operation] = $data;
        }

        return $metrics;
    }

    /**
     * Reset circuit breaker for provider
     */
    public function resetCircuitBreaker(string $provider): void
    {
        Cache::forget("circuit_breaker.{$provider}");

        Log::info('[ResilienceService] Circuit breaker reset', [
            'provider' => $provider,
        ]);
    }

    /**
     * Get overall system health
     */
    public function getSystemHealth(): array
    {
        $providers = ['openai', 'anthropic'];
        $health = [];

        foreach ($providers as $provider) {
            $health[$provider] = [
                'healthy' => $this->isProviderHealthy($provider),
                'circuit_breaker_open' => $this->isCircuitBreakerOpen($provider),
                'metrics' => $this->getProviderMetrics($provider),
            ];
        }

        $overallHealthy = array_reduce(
            $health,
            fn ($carry, $providerHealth) => $carry || $providerHealth['healthy'],
            false
        );

        return [
            'overall_healthy' => $overallHealthy,
            'providers' => $health,
            'timestamp' => now()->toISOString(),
        ];
    }
}
