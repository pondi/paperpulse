<?php

namespace App\Services\AI;

use Throwable;

class ErrorClassificationService
{
    protected array $errorPatterns = [];

    protected array $errorActions = [];

    public function __construct()
    {
        $this->initializeErrorPatterns();
        $this->initializeErrorActions();
    }

    /**
     * Classify error and determine appropriate action
     */
    public function classifyError(Throwable $exception): array
    {
        $message = strtolower($exception->getMessage());
        $code = method_exists($exception, 'getCode') ? $exception->getCode() : 0;

        foreach ($this->errorPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (is_string($pattern) && str_contains($message, $pattern)) {
                    return $this->getErrorAction($category, $exception);
                } elseif (is_int($pattern) && $code === $pattern) {
                    return $this->getErrorAction($category, $exception);
                }
            }
        }

        return $this->getErrorAction('unknown', $exception);
    }

    /**
     * Initialize error classification patterns
     */
    protected function initializeErrorPatterns(): void
    {
        $this->errorPatterns = [
            'rate_limit' => [
                'rate limit exceeded',
                'too many requests',
                'rate_limit_exceeded',
                429,
            ],
            'quota_exceeded' => [
                'quota exceeded',
                'insufficient quota',
                'billing limit',
                'usage limit',
            ],
            'authentication' => [
                'invalid api key',
                'unauthorized',
                'authentication failed',
                'invalid token',
                401,
                403,
            ],
            'network' => [
                'connection timeout',
                'network error',
                'dns resolution failed',
                'connection refused',
                'timeout',
            ],
            'service_unavailable' => [
                'service unavailable',
                'server error',
                'internal server error',
                'bad gateway',
                500, 502, 503, 504,
            ],
            'validation' => [
                'validation failed',
                'invalid input',
                'malformed request',
                'bad request',
                400, 422,
            ],
            'model_error' => [
                'model not found',
                'model overloaded',
                'model unavailable',
                'context length exceeded',
            ],
            'content_filter' => [
                'content filter',
                'content policy',
                'safety filter',
                'harmful content',
            ],
            'parsing_error' => [
                'json decode error',
                'invalid json',
                'parsing failed',
                'malformed response',
            ],
        ];
    }

    /**
     * Initialize error actions
     */
    protected function initializeErrorActions(): void
    {
        $this->errorActions = [
            'rate_limit' => [
                'retry' => true,
                'delay' => 60000, // 1 minute
                'fallback_provider' => true,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'warning',
            ],
            'quota_exceeded' => [
                'retry' => false,
                'fallback_provider' => true,
                'circuit_breaker' => true,
                'user_notification' => true,
                'severity' => 'error',
            ],
            'authentication' => [
                'retry' => false,
                'fallback_provider' => true,
                'circuit_breaker' => true,
                'user_notification' => true,
                'severity' => 'critical',
            ],
            'network' => [
                'retry' => true,
                'delay' => 5000, // 5 seconds
                'fallback_provider' => true,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'warning',
            ],
            'service_unavailable' => [
                'retry' => true,
                'delay' => 30000, // 30 seconds
                'fallback_provider' => true,
                'circuit_breaker' => true,
                'user_notification' => false,
                'severity' => 'error',
            ],
            'validation' => [
                'retry' => false,
                'fallback_provider' => false,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'info',
            ],
            'model_error' => [
                'retry' => true,
                'delay' => 10000, // 10 seconds
                'fallback_provider' => true,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'warning',
            ],
            'content_filter' => [
                'retry' => false,
                'fallback_provider' => true,
                'circuit_breaker' => false,
                'user_notification' => true,
                'severity' => 'warning',
            ],
            'parsing_error' => [
                'retry' => true,
                'delay' => 2000, // 2 seconds
                'fallback_provider' => false,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'warning',
            ],
            'unknown' => [
                'retry' => true,
                'delay' => 5000,
                'fallback_provider' => true,
                'circuit_breaker' => false,
                'user_notification' => false,
                'severity' => 'error',
            ],
        ];
    }

    /**
     * Get error action for category
     */
    protected function getErrorAction(string $category, Throwable $exception): array
    {
        $actions = $this->errorActions[$category] ?? $this->errorActions['unknown'];

        return array_merge($actions, [
            'category' => $category,
            'exception_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => method_exists($exception, 'getCode') ? $exception->getCode() : 0,
            'classified_at' => now()->toISOString(),
        ]);
    }

    /**
     * Check if error is recoverable
     */
    public function isRecoverableError(Throwable $exception): bool
    {
        $classification = $this->classifyError($exception);

        return $classification['retry'] || $classification['fallback_provider'];
    }

    /**
     * Get recommended delay for retry
     */
    public function getRecommendedDelay(Throwable $exception): int
    {
        $classification = $this->classifyError($exception);

        return $classification['delay'] ?? 5000;
    }

    /**
     * Check if error should trigger circuit breaker
     */
    public function shouldTriggerCircuitBreaker(Throwable $exception): bool
    {
        $classification = $this->classifyError($exception);

        return $classification['circuit_breaker'] ?? false;
    }

    /**
     * Get error severity level
     */
    public function getErrorSeverity(Throwable $exception): string
    {
        $classification = $this->classifyError($exception);

        return $classification['severity'] ?? 'error';
    }

    /**
     * Generate user-friendly error message
     */
    public function getUserFriendlyMessage(Throwable $exception): string
    {
        $classification = $this->classifyError($exception);

        return match ($classification['category']) {
            'rate_limit' => 'Service is temporarily busy. Please try again in a few moments.',
            'quota_exceeded' => 'Usage limit has been reached. Please check your subscription or try again later.',
            'authentication' => 'Authentication error. Please check your API configuration.',
            'network' => 'Network connectivity issue. Please check your connection and try again.',
            'service_unavailable' => 'Service is temporarily unavailable. Please try again later.',
            'validation' => 'Invalid input provided. Please check your data and try again.',
            'model_error' => 'AI model error occurred. Trying alternative approach.',
            'content_filter' => 'Content was filtered for safety reasons. Please review your input.',
            'parsing_error' => 'Response parsing error. Retrying with different parameters.',
            default => 'An unexpected error occurred. Please try again or contact support.'
        };
    }
}
