<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;

class RetryService
{
    protected int $maxRetries;

    protected int $baseDelay;

    protected array $retryableErrors;

    public function __construct()
    {
        $this->maxRetries = config('ai.retry.max_attempts', 3);
        $this->baseDelay = config('ai.retry.base_delay', 1000); // milliseconds
        $this->retryableErrors = [
            'rate_limit_exceeded',
            'service_unavailable',
            'timeout',
            'network_error',
            'temporary_failure',
            'validation_failed',
        ];
    }

    /**
     * Execute with retry logic
     */
    public function execute(callable $operation, array $options = []): mixed
    {
        $maxRetries = $options['max_retries'] ?? $this->maxRetries;
        $backoffMultiplier = $options['backoff_multiplier'] ?? config('ai.retry.backoff_multiplier', 2);
        $jitter = $options['jitter'] ?? config('ai.retry.jitter', true);

        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $operation($attempt);

                // If this is a retry attempt that succeeded, log it
                if ($attempt > 0) {
                    Log::info('[RetryService] Operation succeeded after retry', [
                        'attempt' => $attempt + 1,
                        'total_attempts' => $attempt + 1,
                    ]);
                }

                return $result;

            } catch (Exception $e) {
                $lastException = $e;

                // Check if this error is retryable
                if (! $this->isRetryableError($e) || $attempt >= $maxRetries) {
                    break;
                }

                // Calculate delay with exponential backoff and optional jitter
                $delay = $this->calculateDelay($attempt, $backoffMultiplier, $jitter);

                Log::warning('[RetryService] Operation failed, retrying', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'delay_ms' => $delay,
                    'max_retries' => $maxRetries,
                ]);

                // Sleep before retry (convert milliseconds to microseconds)
                usleep($delay * 1000);
            }
        }

        // All retries exhausted
        Log::error('[RetryService] All retry attempts exhausted', [
            'total_attempts' => $maxRetries + 1,
            'final_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new Exception('Operation failed after all retries');
    }

    /**
     * Determine if an error is retryable
     */
    protected function isRetryableError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach ($this->retryableErrors as $retryableError) {
            if (str_contains($message, $retryableError)) {
                return true;
            }
        }

        // Check for specific HTTP status codes that are retryable
        if (method_exists($e, 'getCode')) {
            $retryableCodes = [429, 500, 502, 503, 504];
            if (in_array($e->getCode(), $retryableCodes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay with exponential backoff and jitter
     */
    protected function calculateDelay(int $attempt, float $backoffMultiplier, bool $jitter): int
    {
        $delay = $this->baseDelay * pow($backoffMultiplier, $attempt);

        if ($jitter) {
            // Add jitter (±25% of the delay)
            $jitterAmount = $delay * 0.25;
            $delay += random_int(-$jitterAmount, $jitterAmount);
        }

        // Cap at maximum delay (e.g., 30 seconds)
        $maxDelay = config('ai.retry.max_delay', 30000);

        return min($delay, $maxDelay);
    }
}
