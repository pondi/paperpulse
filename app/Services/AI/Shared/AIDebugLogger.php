<?php

namespace App\Services\AI\Shared;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Structured debug logging for AI interactions, gated by app.debug.
 */
class AIDebugLogger
{
    /**
     * Log the start of an AI analysis.
     */
    public static function analysisStart(string $provider, string $type, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug("[{$provider}] Starting {$type} analysis", array_merge([
            'timestamp' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log model configuration details.
     */
    public static function modelConfiguration(string $provider, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug("[{$provider}] Model configuration", $context);
    }

    /**
     * Log prompt template details and schema summary.
     */
    public static function promptData(string $provider, array $promptData): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug("[{$provider}] Prompt data prepared", [
            'template_name' => $promptData['template_name'] ?? 'unknown',
            'messages_count' => count($promptData['messages'] ?? []),
            'schema_structure' => array_keys($promptData['schema']['properties'] ?? []),
            'schema_required' => $promptData['schema']['required'] ?? [],
            'options' => $promptData['options'] ?? [],
        ]);
    }

    /**
     * Log outbound API request summary (without full messages).
     */
    public static function apiRequest(string $provider, array $payload): void
    {
        if (! config('app.debug')) {
            return;
        }

        $logData = [
            'model' => $payload['model'] ?? 'unknown',
            'messages_count' => count($payload['messages'] ?? []),
        ];

        if (isset($payload['max_completion_tokens'])) {
            $logData['max_completion_tokens'] = $payload['max_completion_tokens'];
        }
        if (isset($payload['temperature'])) {
            $logData['temperature'] = $payload['temperature'];
        }

        Log::debug("[{$provider}] API request payload", $logData);
    }

    /**
     * Log API response summary (ids, usage, counts).
     */
    public static function apiResponse(string $provider, $response): void
    {
        if (! config('app.debug')) {
            return;
        }

        $logData = [
            'response_id' => $response->id ?? 'unknown',
            'model_used' => $response->model ?? 'unknown',
            'usage' => $response->usage ?? null,
        ];

        if (property_exists($response, 'choices')) {
            $logData['choices_count'] = count($response->choices ?? []);
        }
        if (property_exists($response, 'content')) {
            $logData['content_count'] = count($response->content ?? []);
        }

        Log::debug("[{$provider}] API response received", $logData);
    }

    /**
     * Log successful analysis completion and timing.
     */
    public static function analysisComplete(string $provider, array $result, float $startTime): void
    {
        if (! config('app.debug')) {
            return;
        }

        $processingTime = microtime(true) - $startTime;

        Log::debug("[{$provider}] Analysis completed successfully", [
            'processing_time_ms' => round($processingTime * 1000, 2),
            'result_summary' => [
                'success' => $result['success'] ?? false,
                'tokens_used' => $result['tokens_used'] ?? 0,
                'model' => $result['model'] ?? 'unknown',
            ],
        ]);
    }

    /**
     * Log that a fallback attempt is being made.
     */
    public static function fallbackAttempt(string $provider, string $originalError, array $context = []): void
    {
        Log::info("[{$provider}] Attempting fallback", array_merge([
            'original_error' => $originalError,
        ], $context));
    }

    /**
     * Log successful fallback and timing.
     */
    public static function fallbackSuccess(string $provider, float $startTime, array $result): void
    {
        Log::info("[{$provider}] Fallback successful", [
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'tokens_used' => $result['tokens_used'] ?? 0,
        ]);
    }

    /**
     * Log an analysis error with timing and optional stack trace.
     */
    public static function analysisError(string $provider, Exception $e, float $startTime, array $context = []): void
    {
        $processingTime = microtime(true) - $startTime;

        Log::error("[{$provider}] Analysis failed", array_merge([
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'processing_time_ms' => round($processingTime * 1000, 2),
            'stack_trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
        ], $context));
    }
}
