<?php

namespace App\Services\AI\Shared;

use App\Exceptions\AIResponseException;
use App\Models\ProcessingUsageCounter;
use Illuminate\Support\Facades\Context;
use Throwable;

class ProcessingUsageBudget
{
    private static ?array $context = null;

    public static function run(int $userId, string $runId, string $stage, callable $operation, array $limits = []): mixed
    {
        $previous = self::$context;
        self::$context = ['user' => $userId, 'run' => $runId, 'stage' => $stage, 'limits' => $limits, 'reservations' => []];
        try {
            return $operation();
        } catch (Throwable $exception) {
            Context::add('processing_stage', $stage);
            throw $exception;
        } finally {
            self::$context = $previous;
        }
    }

    public static function reserve(int $tokens): void
    {
        self::allocate($tokens, true);
    }

    public static function check(): void
    {
        self::allocate(0, false);
    }

    private static function allocate(int $tokens, bool $consume): void
    {
        if (self::$context === null) {
            return;
        }
        $context = self::$context;
        ProcessingUsageCounter::query()->where('user_id', $context['user'])->where('expires_at', '<', now())->delete();
        $dailyKey = 'daily:'.$context['user'].':'.now()->utc()->format('Y-m-d');
        ProcessingUsageCounter::query()->firstOrCreate(['scope_key' => $dailyKey], ['user_id' => $context['user'], 'usage' => ['calls' => 0, 'reserved_tokens' => 0], 'expires_at' => now()->addDays(2)]);
        (new ProcessingUsageCounter)->getConnection()->transaction(function () use ($context, $tokens, $dailyKey, $consume): void {
            $runKey = self::runKey($context['user'], $context['run']);
            $daily = ProcessingUsageCounter::query()->where('scope_key', $dailyKey)->lockForUpdate()->firstOrFail();
            $runCounter = ProcessingUsageCounter::query()->firstOrCreate(['scope_key' => $runKey], ['user_id' => $context['user'], 'usage' => ['calls' => 0, 'reserved_tokens' => 0, 'stages' => []], 'expires_at' => now()->addDays(2)]);
            $runCounter = ProcessingUsageCounter::query()->whereKey($runCounter->id)->lockForUpdate()->firstOrFail();
            $run = $runCounter->usage;
            $user = $daily->usage;
            $limits = [
                'run_calls' => [$run['calls'] + 1, (int) ($context['limits']['calls'] ?? config('ai.limits.max_calls_per_run')), 'run'],
                'run_tokens' => [$run['reserved_tokens'] + $tokens, (int) ($context['limits']['tokens'] ?? config('ai.limits.max_tokens_per_run')), 'run'],
                'daily_calls' => [$user['calls'] + 1, (int) config('ai.limits.max_calls_per_user_day'), 'daily'],
                'daily_tokens' => [$user['reserved_tokens'] + $tokens, (int) config('ai.limits.max_tokens_per_user_day'), 'daily'],
            ];
            foreach ($limits as $name => [$requested, $limit, $scope]) {
                if ($requested <= $limit) {
                    continue;
                }
                if (($name === 'daily_tokens' && $tokens > $limit) || ($name === 'daily_calls' && $limit < 1)) {
                    $scope = 'request';
                }
                throw new AIResponseException('Processing usage budget exceeded', errorCode: AIResponseException::CODE_USAGE_BUDGET_EXCEEDED, context: [
                    'budget_scope' => $scope,
                    'limit' => $name,
                    'limit_value' => $limit,
                    'retry_after' => $scope === 'daily' ? now()->utc()->addDay()->startOfDay()->addMinutes(5)->toIso8601String() : null,
                    'stage' => $context['stage'],
                    'run_calls' => $run['calls'],
                    'daily_calls' => $user['calls'],
                    'run_reserved_tokens' => $run['reserved_tokens'],
                    'daily_reserved_tokens' => $user['reserved_tokens'],
                    'requested_tokens' => $tokens,
                ]);
            }
            if (! $consume) {
                return;
            }
            $run['calls']++;
            $user['calls']++;
            $run['reserved_tokens'] += $tokens;
            $user['reserved_tokens'] += $tokens;
            $stage = $context['stage'];
            $run['stages'][$stage] ??= ['calls' => 0, 'reserved_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0];
            $run['stages'][$stage]['calls']++;
            $run['stages'][$stage]['reserved_tokens'] += $tokens;
            $runCounter->update(['usage' => $run]);
            $daily->update(['usage' => $user]);
        });
        if ($consume) {
            self::$context['reservations'][] = ['tokens' => $tokens, 'daily_key' => $dailyKey];
        }
    }

    public static function record(int $inputTokens, int $outputTokens): void
    {
        if (self::$context === null) {
            return;
        }
        $context = self::$context;
        $reservation = array_pop(self::$context['reservations']);
        if ($reservation === null) {
            return;
        }
        (new ProcessingUsageCounter)->getConnection()->transaction(function () use ($context, $inputTokens, $outputTokens, $reservation): void {
            $daily = ProcessingUsageCounter::query()->where('scope_key', $reservation['daily_key'])->lockForUpdate()->firstOrFail();
            $key = self::runKey($context['user'], $context['run']);
            $counter = ProcessingUsageCounter::query()->where('scope_key', $key)->lockForUpdate()->firstOrFail();
            $usage = $counter->usage;
            $adjustment = max(0, $inputTokens) + max(0, $outputTokens) - $reservation['tokens'];
            $usage['reserved_tokens'] += $adjustment;
            $usage['stages'][$context['stage']]['reserved_tokens'] += $adjustment;
            $usage['stages'][$context['stage']]['input_tokens'] += max(0, $inputTokens);
            $usage['stages'][$context['stage']]['output_tokens'] += max(0, $outputTokens);
            $counter->update(['usage' => $usage]);
            $dailyUsage = $daily->usage;
            $dailyUsage['reserved_tokens'] += $adjustment;
            $daily->update(['usage' => $dailyUsage]);
        });
    }

    public static function usage(int $userId, string $runId): array
    {
        return ProcessingUsageCounter::query()->where('user_id', $userId)->where('scope_key', self::runKey($userId, $runId))->value('usage') ?? ['calls' => 0, 'reserved_tokens' => 0, 'stages' => []];
    }

    private static function runKey(int $userId, string $runId): string
    {
        return 'run:'.hash('sha256', $userId.':'.$runId);
    }
}
