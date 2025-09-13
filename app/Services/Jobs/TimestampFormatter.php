<?php

namespace App\Services\Jobs;

use Carbon\Carbon;

class TimestampFormatter
{
    public static function format(string|int|null $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return is_int($timestamp)
            ? Carbon::createFromTimestamp($timestamp)->toIso8601String()
            : Carbon::parse($timestamp)->toIso8601String();
    }
}

