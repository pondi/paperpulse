<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavSyncContract;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDav\Support\PathHelper;
use App\Services\PulseDav\Support\S3ListService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PulseDavSyncService implements PulseDavSyncContract
{
    protected $s3Client;

    protected $bucket;

    protected $incomingPrefix;

    public function __construct()
    {
        $this->s3Client = Storage::disk('pulsedav')->getClient();
        $this->bucket = config('filesystems.disks.pulsedav.bucket');
        $this->incomingPrefix = config('services.pulsedav.s3_incoming_prefix', 'incoming/');
    }

    /**
     * List all PulseDav files for a specific user from S3
     */
    public function listUserFiles(User $user): array
    {
        return array_values(iterator_to_array(S3ListService::files(
            $this->s3Client, $this->bucket, PathHelper::userPrefix($this->incomingPrefix, $user->id), $user->id,
        )));
    }

    /**
     * Sync S3 files to database
     */
    public function syncS3Files(User $user): int
    {
        $s3Files = S3ListService::files($this->s3Client, $this->bucket, PathHelper::userPrefix($this->incomingPrefix, $user->id), $user->id);
        $synced = 0;

        foreach ($s3Files as $fileData) {
            // Check if file already exists in database
            $exists = PulseDavFile::where('s3_path', $fileData['s3_path'])
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                PulseDavFile::create([
                    'user_id' => $user->id,
                    's3_path' => $fileData['s3_path'],
                    'filename' => $fileData['filename'],
                    'size' => $fileData['size'],
                    'uploaded_at' => $fileData['uploaded_at'],
                    'status' => 'pending',
                    'file_type' => config('paperpulse.default_pulsedav_type', 'receipt'),
                ]);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * List all PulseDav files and folders for a specific user from S3
     */
    public function listUserFilesWithFolders(User $user): array
    {
        return array_values(iterator_to_array(S3ListService::files(
            $this->s3Client, $this->bucket, PathHelper::userPrefix($this->incomingPrefix, $user->id), $user->id, true,
        )));
    }

    /**
     * Sync S3 files with folder support
     */
    public function syncS3FilesWithFolders(User $user): int
    {
        Log::info('[PulseDavSync] Starting sync with folders', [
            'user_id' => $user->id,
        ]);

        $items = S3ListService::files($this->s3Client, $this->bucket, PathHelper::userPrefix($this->incomingPrefix, $user->id), $user->id, true);

        $synced = 0;
        $skipped = 0;
        $userPrefix = PathHelper::userPrefix($this->incomingPrefix, $user->id);

        foreach ($items as $itemData) {
            // Extract folder info
            $folderInfo = PulseDavFile::extractFolderInfo($itemData['s3_path'], $userPrefix);

            Log::debug('[PulseDavSync] Processing item for sync', [
                's3_path' => $itemData['s3_path'],
                'is_folder' => $itemData['is_folder'],
                'folder_info' => $folderInfo,
            ]);

            // Check if item already exists in database
            $exists = PulseDavFile::where('s3_path', $itemData['s3_path'])
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                try {
                    PulseDavFile::create([
                        'user_id' => $user->id,
                        's3_path' => $itemData['s3_path'],
                        'filename' => $itemData['filename'],
                        'size' => $itemData['size'],
                        'uploaded_at' => $itemData['uploaded_at'] ?? now(),
                        'status' => $itemData['is_folder'] ? 'folder' : 'pending',
                        'file_type' => config('paperpulse.default_pulsedav_type', 'receipt'),
                        'folder_path' => $folderInfo['folder_path'],
                        'parent_folder' => $folderInfo['parent_folder'],
                        'depth' => $folderInfo['depth'],
                        'is_folder' => $itemData['is_folder'],
                    ]);
                    $synced++;

                    Log::debug('[PulseDavSync] Created PulseDavFile record', [
                        's3_path' => $itemData['s3_path'],
                    ]);
                } catch (Exception $e) {
                    Log::error('[PulseDavSync] Failed to create PulseDavFile record', [
                        's3_path' => $itemData['s3_path'],
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            } else {
                $skipped++;
                Log::debug('[PulseDavSync] Item already exists, skipping', [
                    's3_path' => $itemData['s3_path'],
                ]);
            }
        }

        Log::info('[PulseDavSync] Sync completed', [
            'synced' => $synced,
            'skipped' => $skipped,
            'total_items' => $synced + $skipped,
        ]);

        return $synced;
    }

    /**
     * Get processing status for a file
     */
    public function getProcessingStatus(PulseDavFile $s3File): array
    {
        return [
            'id' => $s3File->id,
            'filename' => $s3File->filename,
            'status' => $s3File->status,
            'file_type' => $s3File->file_type,
            'processed_at' => $s3File->processed_at,
            'error_message' => $s3File->error_message,
            'receipt_id' => $s3File->receipt_id,
            'document_id' => $s3File->document_id,
        ];
    }
}
