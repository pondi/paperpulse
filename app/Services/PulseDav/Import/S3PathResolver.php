<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\User;

class S3PathResolver
{
    public static function resolveToRecord(string $s3Path, User $user): ?PulseDavFile
    {
        return PulseDavFile::where('user_id', $user->id)
            ->where('s3_path', $s3Path)
            ->first();
    }

    public static function pathExists(string $s3Path): bool
    {
        return \Storage::disk('pulsedav')->exists($s3Path);
    }
}
