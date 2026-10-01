<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDav\Support\PathHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class S3PathResolver
{
    public static function resolveToRecord(string $s3Path, User $user): ?PulseDavFile
    {
        self::validateOwnedPath($s3Path, $user->id);

        return PulseDavFile::where('user_id', $user->id)
            ->where('s3_path', $s3Path)
            ->first();
    }

    public static function validateOwnedPath(string $s3Path, int $userId): string
    {
        $prefix = PathHelper::userPrefix(config('services.pulsedav.s3_incoming_prefix', 'incoming/'), $userId);
        $segments = explode('/', rtrim($s3Path, '/'));

        if (! str_starts_with($s3Path, $prefix)
            || $s3Path === $prefix
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $s3Path)
            || str_contains($s3Path, '//')
            || array_intersect(['', '.', '..'], $segments)) {
            throw ValidationException::withMessages(['s3_path' => 'Invalid scanner path for this user.']);
        }

        return $s3Path;
    }

    public static function pathExists(string $s3Path, int $userId): bool
    {
        self::validateOwnedPath($s3Path, $userId);

        return str_ends_with($s3Path, '/')
            ? Storage::disk('pulsedav')->directoryExists($s3Path)
            : Storage::disk('pulsedav')->exists($s3Path);
    }
}
