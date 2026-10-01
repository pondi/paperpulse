<?php

namespace App\Services\AI\Shared;

use App\Exceptions\AIResponseException;
use App\Models\ProcessingUsageCounter;

class ProcessingUsageBudget
{
    private static ?array $context = null;

    public static function run(int $userId, string $runId, string $stage, callable $operation): mixed
    {
        $previous = self::$context;
        self::$context = ['user' => $userId, 'run' => $runId, 'stage' => $stage];
        try {
            return $operation();
        } finally {
            self::$context = $previous;
        }
    }

    public static function reserve(int $tokens): void
    {
        if (self::$context === null) {
            return;
        }
        $context = self::$context;
        ProcessingUsageCounter::query()->where('user_id', $context['user'])->where('expires_at', '<', now())->delete();
        $dailyKey = 'daily:'.$context['user'].':'.now()->utc()->format('Y-m-d');
        ProcessingUsageCounter::query()->firstOrCreate(['scope_key' => $dailyKey], ['user_id' => $context['user'], 'usage' => ['calls' => 0, 'reserved_tokens' => 0], 'expires_at' => now()->addDays(2)]);
        (new ProcessingUsageCounter)->getConnection()->transaction(function () use ($context, $tokens, $dailyKey): void {
            $runKey = self::runKey($context['user'], $context['run']);
            $daily = ProcessingUsageCounter::query()->where('scope_key', $dailyKey)->lockForUpdate()->firstOrFail();
            $runCounter = ProcessingUsageCounter::query()->firstOrCreate(['scope_key' => $runKey], ['user_id' => $context['user'], 'usage' => ['calls' => 0, 'reserved_tokens' => 0, 'stages' => []], 'expires_at' => now()->addDays(2)]);
            $run = $runCounter->usage;
            $user = $daily->usage;
            if ($run['calls'] >= (int) config('ai.limits.max_calls_per_run', 10)
                || $user['calls'] >= (int) config('ai.limits.max_calls_per_user_day', 100)
                || (int) config('ai.limits.max_tokens_per_run', 200000) < $run['reserved_tokens'] + $tokens
                || (int) config('ai.limits.max_tokens_per_user_day', 2000000) < $user['reserved_tokens'] + $tokens) {
                throw new AIResponseException('Processing usage budget exceeded');
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
    }

    public static function record(int $inputTokens, int $outputTokens): void
    {
        if (self::$context === null) {
            return;
        }
        $context = self::$context;
        (new ProcessingUsageCounter)->getConnection()->transaction(function () use ($context, $inputTokens, $outputTokens): void {
            $key = self::runKey($context['user'], $context['run']);
            $counter = ProcessingUsageCounter::query()->where('scope_key', $key)->lockForUpdate()->firstOrFail();
            $usage = $counter->usage;
            $usage['stages'][$context['stage']]['input_tokens'] += max(0, $inputTokens);
            $usage['stages'][$context['stage']]['output_tokens'] += max(0, $outputTokens);
            $counter->update(['usage' => $usage]);
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
