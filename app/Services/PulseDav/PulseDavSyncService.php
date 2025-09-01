<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavSyncContract;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Notifications\ScannerFilesImported;
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
        $prefix = $this->incomingPrefix.$user->id.'/';

        try {
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $files = [];
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    // Skip directories
                    if (substr($object['Key'], -1) === '/') {
                        continue;
                    }

                    $files[] = [
                        's3_path' => $object['Key'],
                        'filename' => basename($object['Key']),
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            return $files;
        } catch (\Exception $e) {
            Log::error('[PulseDavSync] Failed to list S3 files', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync S3 files to database
     */
    public function syncS3Files(User $user): int
    {
        $s3Files = $this->listUserFiles($user);
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

        // Send notification if files were synced
        if ($synced > 0 && $user->preferences) {
            if ($user->preferences->notify_scanner_import) {
                try {
                    $user->notify(new ScannerFilesImported($synced));
                } catch (\Exception $e) {
                    Log::warning('[PulseDavSync] Failed to send scanner import notification', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $synced;
    }

    /**
     * List all PulseDav files and folders for a specific user from S3
     */
    public function listUserFilesWithFolders(User $user): array
    {
        $prefix = $this->incomingPrefix.$user->id.'/';

        try {
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $items = [];
            $folders = [];

            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    $key = $object['Key'];
                    $relativePath = str_replace($prefix, '', $key);

                    // Skip empty paths
                    if (empty($relativePath)) {
                        continue;
                    }

                    // Parse folder structure
                    $parts = explode('/', $relativePath);
                    $currentPath = '';

                    // Create folder entries
                    for ($i = 0; $i < count($parts) - 1; $i++) {
                        $currentPath .= ($i > 0 ? '/' : '').$parts[$i];
                        $parentPath = $i > 0 ? implode('/', array_slice($parts, 0, $i)) : null;

                        if (! isset($folders[$currentPath])) {
                            $folders[$currentPath] = [
                                's3_path' => $prefix.$currentPath.'/',
                                'filename' => $parts[$i],
                                'folder_path' => $currentPath,
                                'parent_folder' => $parentPath,
                                'depth' => $i,
                                'is_folder' => true,
                                'size' => 0,
                                'uploaded_at' => null,
                            ];
                        }
                    }

                    // Add file entry
                    $folderPath = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : null;
                    $items[] = [
                        's3_path' => $object['Key'],
                        'filename' => basename($object['Key']),
                        'folder_path' => $folderPath,
                        'parent_folder' => $folderPath ? basename($folderPath) : null,
                        'depth' => count($parts) - 1,
                        'is_folder' => false,
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            // Merge folders and files
            return array_merge(array_values($folders), $items);
        } catch (\Exception $e) {
            Log::error('[PulseDavSync] Failed to list S3 files with folders', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync S3 files with folder support
     */
    public function syncS3FilesWithFolders(User $user): int
    {
        Log::info('[PulseDavSync] Starting sync with folders', [
            'user_id' => $user->id,
        ]);

        $items = $this->listUserFilesWithFolders($user);

        Log::info('[PulseDavSync] Found items in S3', [
            'total_items' => count($items),
            'sample_items' => array_slice($items, 0, 5),
        ]);

        $synced = 0;
        $skipped = 0;
        $userPrefix = $this->incomingPrefix.$user->id.'/';

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
                } catch (\Exception $e) {
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
            'total_items' => count($items),
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
