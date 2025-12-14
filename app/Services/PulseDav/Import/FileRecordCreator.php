<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;

class FileRecordCreator
{
    public static function createFromS3Path(string $s3Path, User $user): PulseDavFile
    {
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
        $userPrefix = 'incoming/'.$user->id.'/';
        $info = PulseDavFile::extractFolderInfo($s3Path, $userPrefix);
        $info['filename'] = basename($s3Path);
        $info['is_folder'] = substr($s3Path, -1) === '/';

        return $info;
    }

    private static function getS3Metadata(string $s3Path): array
    {
        try {
            return [
                'size' => Storage::disk('pulsedav')->size($s3Path) ?? 0,
                'modified' => Storage::disk('pulsedav')->lastModified($s3Path)
                    ? Carbon::createFromTimestamp(Storage::disk('pulsedav')->lastModified($s3Path))
                    : now(),
            ];
        } catch (Exception $e) {
            return ['size' => 0, 'modified' => now()];
        }
    }
}
