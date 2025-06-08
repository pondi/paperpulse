<?php

namespace App\Services;

use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\User;
use App\Notifications\ScannerFilesImported;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PulseDavService
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
    public function listUserFiles(User $user)
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
            Log::error('Failed to list S3 files', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync S3 files to database
     */
    public function syncS3Files(User $user)
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
                    'file_type' => config('receipt-scanner.default_pulsedav_type', 'receipt'),
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
                    Log::warning('Failed to send scanner import notification', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $synced;
    }

    /**
     * Download file from S3
     */
    public function downloadFile($s3Path)
    {
        try {
            return Storage::disk('pulsedav')->get($s3Path);
        } catch (\Exception $e) {
            Log::error('Failed to download S3 file', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from S3 (soft delete with retention)
     */
    public function deleteFile(PulseDavFile $s3File)
    {
        try {
            // Soft delete in database first
            $s3File->delete();

            // Optionally move file to archive folder in S3
            $archivePath = 'archive/'.$s3File->s3_path;

            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket.'/'.$s3File->s3_path,
                'Key' => $archivePath,
            ]);

            // Delete original file
            Storage::disk('pulsedav')->delete($s3File->s3_path);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete S3 file', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get processing status for a file
     */
    public function getProcessingStatus(PulseDavFile $s3File)
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

    /**
     * Process multiple files
     */
    public function processFiles(array $fileIds, User $user, $fileType = 'receipt')
    {
        $files = PulseDavFile::whereIn('id', $fileIds)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        $queued = 0;
        foreach ($files as $file) {
            // Update file type before processing
            $file->update(['file_type' => $fileType]);
            
            // Dispatch job to process this file
            \App\Jobs\ProcessPulseDavFile::dispatch($file);
            $file->markAsProcessing();
            $queued++;
        }

        return $queued;
    }

    /**
     * Generate a temporary download URL for a file
     */
    public function getTemporaryUrl(PulseDavFile $s3File, $expiration = 60)
    {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3File->s3_path,
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} minutes");

            return (string) $request->getUri();
        } catch (\Exception $e) {
            Log::error('Failed to generate temporary URL', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List all PulseDav files and folders for a specific user from S3
     */
    public function listUserFilesWithFolders(User $user)
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
                        $currentPath .= ($i > 0 ? '/' : '') . $parts[$i];
                        $parentPath = $i > 0 ? implode('/', array_slice($parts, 0, $i)) : null;
                        
                        if (!isset($folders[$currentPath])) {
                            $folders[$currentPath] = [
                                's3_path' => $prefix . $currentPath . '/',
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
            Log::error('Failed to list S3 files with folders', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build hierarchical folder structure from S3 objects
     */
    public function buildFolderHierarchy(array $items)
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
            if (!$item['is_folder']) {
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
    public function getFolderContents(User $user, string $folderPath = '')
    {
        $prefix = $this->incomingPrefix . $user->id . '/';
        if ($folderPath) {
            $prefix .= $folderPath . '/';
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
                foreach ($objects['CommonPrefixes'] as $prefix) {
                    $folderName = rtrim(str_replace($prefix, '', $prefix['Prefix']), '/');
                    $folderName = basename($folderName);
                    
                    $items[] = [
                        'name' => $folderName,
                        's3_path' => $prefix['Prefix'],
                        'path' => $prefix['Prefix'], // Keep for backward compatibility
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
                        'path' => $object['Key'], // Keep for backward compatibility
                        'is_folder' => false,
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }
            
            return $items;
        } catch (\Exception $e) {
            Log::error('Failed to get folder contents', [
                'user_id' => $user->id,
                'folder_path' => $folderPath,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Sync S3 files with folder support
     */
    public function syncS3FilesWithFolders(User $user)
    {
        $items = $this->listUserFilesWithFolders($user);
        $synced = 0;
        $userPrefix = $this->incomingPrefix . $user->id . '/';

        foreach ($items as $itemData) {
            // Extract folder info
            $folderInfo = PulseDavFile::extractFolderInfo($itemData['s3_path'], $userPrefix);
            
            // Check if item already exists in database
            $exists = PulseDavFile::where('s3_path', $itemData['s3_path'])
                ->where('user_id', $user->id)
                ->exists();

            if (!$exists) {
                PulseDavFile::create([
                    'user_id' => $user->id,
                    's3_path' => $itemData['s3_path'],
                    'filename' => $itemData['filename'],
                    'size' => $itemData['size'],
                    'uploaded_at' => $itemData['uploaded_at'] ?? now(),
                    'status' => $itemData['is_folder'] ? 'folder' : 'pending',
                    'file_type' => config('receipt-scanner.default_pulsedav_type', 'receipt'),
                    'folder_path' => $folderInfo['folder_path'],
                    'parent_folder' => $folderInfo['parent_folder'],
                    'depth' => $folderInfo['depth'],
                    'is_folder' => $itemData['is_folder'],
                ]);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Import selected files/folders with tags
     */
    public function importSelections(User $user, array $selections, array $options = [])
    {
        $fileType = $options['file_type'] ?? 'receipt';
        $tagIds = $options['tag_ids'] ?? [];
        $notes = $options['notes'] ?? null;
        
        // Create import batch
        $batch = PulseDavImportBatch::create([
            'user_id' => $user->id,
            'imported_at' => now(),
            'file_count' => 0,
            'tag_ids' => $tagIds,
            'notes' => $notes,
        ]);
        
        $imported = 0;
        
        foreach ($selections as $selection) {
            // Get the file/folder from database
            $item = PulseDavFile::where('user_id', $user->id)
                ->where('s3_path', $selection['s3_path'])
                ->first();
                
            if (!$item) {
                continue;
            }
            
            if ($item->is_folder) {
                // Import all files in folder
                $files = PulseDavFile::where('user_id', $user->id)
                    ->where('folder_path', 'like', $item->folder_path . '%')
                    ->filesOnly()
                    ->whereIn('status', ['pending', 'failed'])
                    ->get();
                    
                foreach ($files as $file) {
                    $this->importFile($file, $batch, $fileType, $tagIds);
                    $imported++;
                }
            } else {
                // Import single file
                if ($item->isProcessable()) {
                    $this->importFile($item, $batch, $fileType, $tagIds);
                    $imported++;
                }
            }
        }
        
        // Update batch file count
        $batch->update(['file_count' => $imported]);
        
        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
        ];
    }

    /**
     * Import a single file
     */
    protected function importFile(PulseDavFile $file, PulseDavImportBatch $batch, string $fileType, array $tagIds)
    {
        // Get inherited tags from folders
        $inheritedTags = $file->inherited_tags->pluck('id')->toArray();
        $allTagIds = array_unique(array_merge($tagIds, $inheritedTags));
        
        // Update file with import info
        $file->update([
            'file_type' => $fileType,
            'import_batch_id' => $batch->id,
            'status' => 'processing',
        ]);
        
        // Dispatch processing job with tags
        \App\Jobs\ProcessPulseDavFile::dispatch($file, $allTagIds);
    }

    /**
     * Update folder tags
     */
    public function updateFolderTags(User $user, string $folderPath, array $tagIds)
    {
        $folder = PulseDavFile::where('user_id', $user->id)
            ->where('folder_path', $folderPath)
            ->where('is_folder', true)
            ->first();
            
        if (!$folder) {
            // Create virtual folder entry
            $folder = PulseDavFile::create([
                'user_id' => $user->id,
                's3_path' => $this->incomingPrefix . $user->id . '/' . $folderPath . '/',
                'filename' => basename($folderPath),
                'folder_path' => $folderPath,
                'parent_folder' => dirname($folderPath) !== '.' ? dirname($folderPath) : null,
                'depth' => substr_count($folderPath, '/'),
                'is_folder' => true,
                'status' => 'folder',
                'size' => 0,
            ]);
        }
        
        $folder->update(['folder_tag_ids' => $tagIds]);
        
        return $folder;
    }
}
