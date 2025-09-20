<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavFolderContract;
use App\Models\PulseDavFile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PulseDavFolderService implements PulseDavFolderContract
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
     * Build hierarchical folder structure from S3 objects
     */
    public function buildFolderHierarchy(array $items): array
    {
        $hierarchy = [];
        $folderMap = [];

        // First pass: create all folders
        foreach ($items as $item) {
            if ($item['is_folder']) {
                $folderMap[$item['folder_path']] = [
                    'path' => $item['folder_path'],
                    'name' => $item['filename'],
                    'is_folder' => true,
                    'children' => [],
                    'files' => [],
                    'metadata' => $item,
                ];
            }
        }

        // Second pass: build hierarchy
        foreach ($folderMap as $path => $folder) {
            if ($folder['metadata']['parent_folder'] === null) {
                $hierarchy[] = &$folderMap[$path];
            } else {
                $parentPath = $folder['metadata']['parent_folder'];
                if (isset($folderMap[$parentPath])) {
                    $folderMap[$parentPath]['children'][] = &$folderMap[$path];
                }
            }
        }

        // Third pass: add files to folders
        foreach ($items as $item) {
            if (! $item['is_folder']) {
                if ($item['folder_path'] === null) {
                    // Root level file
                    $hierarchy[] = $item;
                } elseif (isset($folderMap[$item['folder_path']])) {
                    $folderMap[$item['folder_path']]['files'][] = $item;
                }
            }
        }

        return $hierarchy;
    }

    /**
     * Get folder contents for a specific path
     */
    public function getFolderContents(User $user, string $folderPath = ''): array
    {
        $prefix = $this->incomingPrefix.$user->id.'/';
        if ($folderPath) {
            $prefix .= $folderPath.'/';
        }

        try {
            // Use delimiter to get immediate children only
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/',
            ]);

            $items = [];

            // Add folders (CommonPrefixes)
            if (isset($objects['CommonPrefixes'])) {
                foreach ($objects['CommonPrefixes'] as $prefixItem) {
                    $folderName = rtrim(str_replace($prefix, '', $prefixItem['Prefix']), '/');

                    $items[] = [
                        'name' => $folderName,
                        's3_path' => $prefixItem['Prefix'],
                        'path' => $prefixItem['Prefix'],
                        'is_folder' => true,
                        'size' => 0,
                        'uploaded_at' => null,
                    ];
                }
            }

            // Add files
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    // Skip the folder itself
                    if ($object['Key'] === $prefix) {
                        continue;
                    }

                    $items[] = [
                        'name' => basename($object['Key']),
                        's3_path' => $object['Key'],
                        'path' => $object['Key'],
                        'is_folder' => false,
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            Log::debug('[PulseDavFolder] Retrieved folder contents', [
                'user_id' => $user->id,
                'folder_path' => $folderPath,
                'items_count' => count($items),
            ]);

            return $items;
        } catch (\Exception $e) {
            Log::error('[PulseDavFolder] Failed to get folder contents', [
                'user_id' => $user->id,
                'folder_path' => $folderPath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Update folder tags
     */
    public function updateFolderTags(User $user, string $folderPath, array $tagIds): PulseDavFile
    {
        $folder = PulseDavFile::where('user_id', $user->id)
            ->where('folder_path', $folderPath)
            ->where('is_folder', true)
            ->first();

        if (! $folder) {
            // Create virtual folder entry
            $folder = PulseDavFile::create([
                'user_id' => $user->id,
                's3_path' => $this->incomingPrefix.$user->id.'/'.$folderPath.'/',
                'filename' => basename($folderPath),
                'folder_path' => $folderPath,
                'parent_folder' => dirname($folderPath) !== '.' ? dirname($folderPath) : null,
                'depth' => substr_count($folderPath, '/'),
                'is_folder' => true,
                'status' => 'folder',
                'size' => 0,
            ]);

            Log::info('[PulseDavFolder] Created virtual folder entry', [
                'folder_id' => $folder->id,
                'folder_path' => $folderPath,
                'user_id' => $user->id,
            ]);
        }

        $folder->update(['folder_tag_ids' => $tagIds]);

        Log::info('[PulseDavFolder] Updated folder tags', [
            'folder_id' => $folder->id,
            'folder_path' => $folderPath,
            'tag_ids' => $tagIds,
        ]);

        return $folder;
    }

    /**
     * Get folder statistics
     */
    public function getFolderStats(User $user, ?string $folderPath = null): array
    {
        $query = PulseDavFile::where('user_id', $user->id);

        if ($folderPath !== null) {
            if ($folderPath === '') {
                // Root level files only
                $query->whereNull('folder_path');
            } else {
                // Files in specific folder (including subfolders)
                $query->where('folder_path', 'like', $folderPath.'%');
            }
        }

        $stats = [
            'total_files' => $query->clone()->filesOnly()->count(),
            'pending_files' => $query->clone()->filesOnly()->where('status', 'pending')->count(),
            'processing_files' => $query->clone()->filesOnly()->where('status', 'processing')->count(),
            'completed_files' => $query->clone()->filesOnly()->where('status', 'completed')->count(),
            'failed_files' => $query->clone()->filesOnly()->where('status', 'failed')->count(),
            'total_folders' => $query->clone()->foldersOnly()->count(),
            'total_size' => $query->clone()->filesOnly()->sum('size'),
        ];

        return $stats;
    }

    /**
     * Get all folders for a user
     */
    public function getUserFolders(User $user): array
    {
        $folders = PulseDavFile::where('user_id', $user->id)
            ->where('is_folder', true)
            ->orderBy('folder_path')
            ->get()
            ->map(function ($folder) {
                return [
                    'id' => $folder->id,
                    'path' => $folder->folder_path,
                    'name' => $folder->filename,
                    'parent' => $folder->parent_folder,
                    'depth' => $folder->depth,
                    'tag_ids' => $folder->folder_tag_ids ?? [],
                    'file_count' => $this->getFolderFileCount($folder),
                ];
            })
            ->toArray();

        return $folders;
    }

    /**
     * Get file count for a specific folder
     */
    protected function getFolderFileCount(PulseDavFile $folder): int
    {
        return PulseDavFile::where('user_id', $folder->user_id)
            ->where('folder_path', 'like', $folder->folder_path.'%')
            ->filesOnly()
            ->count();
    }

    /**
     * Create a virtual folder structure
     */
    public function createVirtualFolder(User $user, string $folderPath): PulseDavFile
    {
        // Check if folder already exists
        $existingFolder = PulseDavFile::where('user_id', $user->id)
            ->where('folder_path', $folderPath)
            ->where('is_folder', true)
            ->first();

        if ($existingFolder) {
            return $existingFolder;
        }

        // Create the folder
        $folder = PulseDavFile::create([
            'user_id' => $user->id,
            's3_path' => $this->incomingPrefix.$user->id.'/'.$folderPath.'/',
            'filename' => basename($folderPath),
            'folder_path' => $folderPath,
            'parent_folder' => dirname($folderPath) !== '.' ? dirname($folderPath) : null,
            'depth' => substr_count($folderPath, '/'),
            'is_folder' => true,
            'status' => 'folder',
            'size' => 0,
        ]);

        Log::info('[PulseDavFolder] Created virtual folder', [
            'folder_id' => $folder->id,
            'folder_path' => $folderPath,
            'user_id' => $user->id,
        ]);

        return $folder;
    }

    /**
     * Delete a folder and all its contents
     */
    public function deleteFolder(User $user, string $folderPath): int
    {
        $deletedCount = 0;

        // Find all files in the folder
        $files = PulseDavFile::where('user_id', $user->id)
            ->where('folder_path', 'like', $folderPath.'%')
            ->get();

        foreach ($files as $file) {
            try {
                if (! $file->is_folder && Storage::disk('pulsedav')->exists($file->s3_path)) {
                    Storage::disk('pulsedav')->delete($file->s3_path);
                }
                $file->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                Log::error('[PulseDavFolder] Failed to delete file in folder', [
                    'file_id' => $file->id,
                    'folder_path' => $folderPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[PulseDavFolder] Deleted folder and contents', [
            'folder_path' => $folderPath,
            'user_id' => $user->id,
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }
}
