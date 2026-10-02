<?php

namespace App\Services\PulseDav\Support;

use App\Services\PulseDav\Import\S3PathResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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

        return S3PathResolver::validateOwnedPath($prefix.self::normalizeFolderPath($folderPath).'/', $userId);
    }

    public static function normalizeFolderPath(string $folderPath): string
    {
        $path = trim($folderPath, '/');
        if ($path !== '' && (preg_match('/[\\x00-\\x1f\\x7f\\\\\\\\]/', $path)
            || array_intersect(['', '.', '..'], explode('/', $path)))) {
            throw ValidationException::withMessages(['folder_path' => 'Invalid scanner folder path.']);
        }

        return $path;
    }

    public static function withinFolder(Builder $query, string $folderPath): Builder
    {
        $path = self::normalizeFolderPath($folderPath);
        $descendants = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path).'/%';

        return $query->where(function (Builder $query) use ($path, $descendants): void {
            $query->where('folder_path', $path)
                ->orWhereRaw("folder_path LIKE ? ESCAPE '!'", [$descendants]);
        });
    }
}
