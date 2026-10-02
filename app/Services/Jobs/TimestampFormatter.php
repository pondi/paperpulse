<?php

namespace App\Services\Jobs;

use Carbon\Carbon;

class TimestampFormatter
{
    public static function format(string|int|null $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        return is_int($timestamp)
            ? Carbon::createFromTimestamp($timestamp, 'UTC')->toIso8601String()
            : Carbon::parse($timestamp, config('app.timezone'))->utc()->toIso8601String();
    }
}
