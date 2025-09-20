<?php

namespace App\Services\PulseDav\Support;

class PathHelper
{
    public static function userPrefix(string $incomingPrefix, int $userId): string
    {
        $prefix = rtrim($incomingPrefix, '/').'/'.$userId.'/';

        return $prefix;
    }

    public static function folderS3Path(string $incomingPrefix, int $userId, string $folderPath): string
    {
        $prefix = self::userPrefix($incomingPrefix, $userId);

        return $prefix.trim($folderPath, '/').'/';
    }
}
