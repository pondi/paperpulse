<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDav\Support\PathHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FileRecordCreator
{
    public static function createFromS3Path(string $s3Path, User $user): PulseDavFile
    {
        $s3Path = S3PathResolver::validateOwnedPath($s3Path, $user->id);
        if (! S3PathResolver::pathExists($s3Path, $user->id)) {
            throw ValidationException::withMessages(['s3_path' => 'Scanner path does not exist.']);
        }

        $fileInfo = self::extractFileInfo($s3Path, $user);
        $metadata = self::getS3Metadata($s3Path);

        return PulseDavFile::create([
            'user_id' => $user->id,
            's3_path' => $s3Path,
            'filename' => $fileInfo['filename'],
            'size' => $metadata['size'],
            'uploaded_at' => $metadata['modified'] ?? now(),
            'status' => 'pending',
            'file_type' => 'receipt',
            'folder_path' => $fileInfo['folder_path'],
            'parent_folder' => $fileInfo['parent_folder'],
            'depth' => $fileInfo['depth'],
            'is_folder' => $fileInfo['is_folder'],
        ]);
    }

    private static function extractFileInfo(string $s3Path, User $user): array
    {
        $userPrefix = PathHelper::userPrefix(config('services.pulsedav.s3_incoming_prefix', 'incoming/'), $user->id);
        $info = PulseDavFile::extractFolderInfo($s3Path, $userPrefix);
        $info['filename'] = basename($s3Path);
        $info['is_folder'] = substr($s3Path, -1) === '/';

        return $info;
    }

    private static function getS3Metadata(string $s3Path): array
    {
        if (str_ends_with($s3Path, '/')) {
            return ['size' => 0, 'modified' => now()];
        }

        return [
            'size' => Storage::disk('pulsedav')->size($s3Path),
            'modified' => Carbon::createFromTimestamp(Storage::disk('pulsedav')->lastModified($s3Path)),
        ];
    }
}
