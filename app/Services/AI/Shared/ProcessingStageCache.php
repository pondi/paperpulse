<?php

namespace App\Services\AI\Shared;

use App\Exceptions\AIResponseException;
use Illuminate\Support\Facades\Cache;

class ProcessingStageCache
{
    public static function remember(int $userId, string $contentHash, string $stage, array $version, callable $operation, ?string $fileGuid = null): array
    {
        $key = self::key($userId, $contentHash, $stage, $version);
        if ($fileGuid !== null) {
            $index = 'processing_stage_index:'.$fileGuid;
            Cache::lock($index.':lock', 10)->block(5, function () use ($index, $key): void {
                Cache::put($index, array_unique([...Cache::get($index, []), $key]), now()->addHours(192));
            });
        }

        return Cache::lock($key.':lock', 660)->block(5, function () use ($key, $operation): array {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            $result = $operation();
            if (! is_array($result) || $result === []) {
                throw new AIResponseException('The processing stage returned no structured result');
            }
            if (($result['success'] ?? true) === true) {
                Cache::put($key, $result, now()->addHours(max(1, min(168, (int) config('ai.limits.stage_cache_hours', 24)))));
            }

            return $result;
        });
    }

    public static function clear(string $fileGuid): void
    {
        $index = 'processing_stage_index:'.$fileGuid;
        foreach (Cache::get($index, []) as $key) {
            Cache::forget($key);
        }
        Cache::forget($index);
    }

    public static function key(int $userId, string $contentHash, string $stage, array $version): string
    {
        return 'processing_stage:'.hash('sha256', json_encode(self::canonicalize([
            'version' => 1, 'user' => $userId, 'content' => $contentHash, 'stage' => $stage, 'configuration' => $version,
        ]), JSON_THROW_ON_ERROR));
    }

    private static function canonicalize(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::canonicalize($value);
            }
        }

        return $values;
    }
}
