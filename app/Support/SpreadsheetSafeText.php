<?php

declare(strict_types=1);

namespace App\Support;

class SpreadsheetSafeText
{
    public static function format(string $value): string
    {
        return preg_match('/^(?:[\x00-\x20]*[=+\-@]| *[\x00-\x1f])/', $value)
            ? "'".$value
            : $value;
    }
}
