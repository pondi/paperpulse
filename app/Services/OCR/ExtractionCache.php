<?php

namespace App\Services\OCR;

use Illuminate\Support\Facades\Cache;

class ExtractionCache
{
    public static function get(string $fileGuid): ?array
    {
        $cacheKey = self::key($fileGuid);
        $text = Cache::get($cacheKey);
        if ($text === null) {
            return null;
        }

        return [
            'text' => $text,
            'structured' => Cache::get("{$cacheKey}.structured", []),
        ];
    }

    public static function put(string $fileGuid, string $text, array $structuredData): void
    {
        $cacheKey = self::key($fileGuid);
        $ttl = now()->addDays(config('ai.ocr.options.cache_duration', 7));
        Cache::put($cacheKey, $text, $ttl);
        Cache::put("{$cacheKey}.structured", $structuredData, $ttl);
    }

    public static function clear(string $fileGuid): void
    {
        $cacheKey = self::key($fileGuid);
        Cache::forget($cacheKey);
        Cache::forget("{$cacheKey}.structured");
    }

    private static function key(string $fileGuid): string
    {
        return "text_extraction.{$fileGuid}";
    }
}
