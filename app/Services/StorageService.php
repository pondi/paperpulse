<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class StorageService
{
    protected $incomingDisk = null;
    protected $storageDisk = null;
    protected $incomingPrefix;
    
    public function __construct()
    {
        // Delay initialization to avoid issues during bootstrap
        $this->incomingPrefix = 'incoming/';
    }
    
    /**
     * Configure the dual S3 bucket disks
     */
    protected function configureDualBuckets(): void
    {
        if ($this->incomingDisk !== null && $this->storageDisk !== null) {
            return; // Already configured
        }
        
        try {
            $this->incomingDisk = Storage::disk('pulsedav');
            $this->storageDisk = Storage::disk('paperpulse');
        } catch (Exception $e) {
            Log::error('Failed to configure S3 disks: ' . $e->getMessage());
            // Fall back to local storage if S3 is not configured
            $this->incomingDisk = Storage::disk('local');
            $this->storageDisk = Storage::disk('local');
        }
    }
    
    /**
     * Upload a file to the incoming bucket
     * 
     * @param string $content File content
     * @param int $userId User ID for scoping
     * @param string $filename Original filename
     * @return string Path to the uploaded file
     */
    public function uploadToIncoming(string $content, int $userId, string $filename): string
    {
        $this->configureDualBuckets();
        
        try {
            $path = $this->generateIncomingPath($userId, $filename);
            
            $success = $this->incomingDisk->put($path, $content);
            
            if (!$success) {
                throw new Exception('Failed to upload file to incoming bucket');
            }
            
            Log::info('[StorageService] File uploaded to incoming bucket', [
                'user_id' => $userId,
                'path' => $path,
                'size' => strlen($content),
            ]);
            
            return $path;
        } catch (Exception $e) {
            Log::error('[StorageService] Upload to incoming failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'filename' => $filename,
            ]);
            throw $e;
        }
    }
    
    /**
     * Move a file from incoming to storage bucket
     * 
     * @param string $incomingPath Path in incoming bucket
     * @param int $userId User ID for scoping
     * @param string $guid File GUID for unique path
     * @param string $fileType 'receipt' or 'document'
     * @param string $extension File extension
     * @return string Path in storage bucket
     */
    public function moveToStorage(string $incomingPath, int $userId, string $guid, string $fileType, string $extension): string
    {
        $this->configureDualBuckets();
        
        try {
            // Read from incoming bucket
            if (!$this->incomingDisk->exists($incomingPath)) {
                throw new Exception("File not found in incoming bucket: {$incomingPath}");
            }
            
            $content = $this->incomingDisk->get($incomingPath);
            
            // Generate storage path
            $storagePath = $this->generateStoragePath($userId, $guid, $fileType, 'original', $extension);
            
            // Write to storage bucket
            $success = $this->storageDisk->put($storagePath, $content);
            
            if (!$success) {
                throw new Exception('Failed to move file to storage bucket');
            }
            
            // Delete from incoming bucket
            $this->incomingDisk->delete($incomingPath);
            
            Log::info('[StorageService] File moved to storage', [
                'incoming_path' => $incomingPath,
                'storage_path' => $storagePath,
                'user_id' => $userId,
                'file_type' => $fileType,
            ]);
            
            return $storagePath;
        } catch (Exception $e) {
            Log::error('[StorageService] Move to storage failed', [
                'error' => $e->getMessage(),
                'incoming_path' => $incomingPath,
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }
    
    /**
     * Store a file directly to the storage bucket
     * 
     * @param string $content File content
     * @param int $userId User ID for scoping
     * @param string $guid File GUID for unique path
     * @param string $fileType 'receipt' or 'document'
     * @param string $variant 'original', 'processed', etc.
     * @param string $extension File extension
     * @return string Path in storage bucket
     */
    public function storeFile(string $content, int $userId, string $guid, string $fileType, string $variant, string $extension): string
    {
        $this->configureDualBuckets();
        
        try {
            $path = $this->generateStoragePath($userId, $guid, $fileType, $variant, $extension);
            
            $success = $this->storageDisk->put($path, $content);
            
            if (!$success) {
                throw new Exception('Failed to store file');
            }
            
            Log::info('[StorageService] File stored', [
                'path' => $path,
                'user_id' => $userId,
                'file_type' => $fileType,
                'variant' => $variant,
            ]);
            
            return $path;
        } catch (Exception $e) {
            Log::error('[StorageService] Store file failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'file_id' => $fileId,
            ]);
            throw $e;
        }
    }
    
    /**
     * Get a file from the storage bucket
     * 
     * @param string $path Path in storage bucket
     * @return string|null File content
     */
    public function getFile(string $path): ?string
    {
        $this->configureDualBuckets();
        
        try {
            if (!$this->storageDisk->exists($path)) {
                Log::warning('[StorageService] File not found', ['path' => $path]);
                return null;
            }
            
            return $this->storageDisk->get($path);
        } catch (Exception $e) {
            Log::error('[StorageService] Get file failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }
    
    /**
     * Get a file's content by user and GUID
     * 
     * @param int $userId User ID
     * @param string $guid File GUID
     * @param string $fileType 'receipt' or 'document'
     * @param string $variant 'original', 'processed', etc.
     * @param string $extension File extension
     * @return string|null File content
     */
    public function getFileByUserAndGuid(int $userId, string $guid, string $fileType, string $variant, string $extension): ?string
    {
        $path = $this->generateStoragePath($userId, $guid, $fileType, $variant, $extension);
        return $this->getFile($path);
    }
    
    /**
     * Get a temporary URL for a file in the storage bucket
     * 
     * @param string $path Path in storage bucket
     * @param int $expirationMinutes Expiration time in minutes
     * @return string|null Temporary URL
     */
    public function getTemporaryUrl(string $path, int $expirationMinutes = 60): ?string
    {
        $this->configureDualBuckets();
        
        try {
            if (!$this->storageDisk->exists($path)) {
                Log::warning('[StorageService] File not found for URL generation', ['path' => $path]);
                return null;
            }
            
            return $this->storageDisk->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        } catch (Exception $e) {
            Log::error('[StorageService] Temporary URL generation failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }
    
    /**
     * Delete a file from the storage bucket
     * 
     * @param string $path Path in storage bucket
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        $this->configureDualBuckets();
        
        try {
            if (!$this->storageDisk->exists($path)) {
                Log::warning('[StorageService] File not found for deletion', ['path' => $path]);
                return true; // Consider it success if file doesn't exist
            }
            
            $success = $this->storageDisk->delete($path);
            
            if ($success) {
                Log::info('[StorageService] File deleted', ['path' => $path]);
            }
            
            return $success;
        } catch (Exception $e) {
            Log::error('[StorageService] Delete file failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return false;
        }
    }
    
    /**
     * List files in the incoming bucket for a user
     * 
     * @param int $userId User ID
     * @return array List of file paths
     */
    public function listIncomingFiles(int $userId): array
    {
        $this->configureDualBuckets();
        
        try {
            $prefix = $this->incomingPrefix . $userId . '/';
            $files = $this->incomingDisk->files($prefix);
            
            Log::debug('[StorageService] Listed incoming files', [
                'user_id' => $userId,
                'count' => count($files),
            ]);
            
            return $files;
        } catch (Exception $e) {
            Log::error('[StorageService] List incoming files failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return [];
        }
    }
    
    /**
     * Generate a path for the incoming bucket
     * 
     * @param int $userId User ID
     * @param string $filename Original filename
     * @return string Generated path
     */
    protected function generateIncomingPath(int $userId, string $filename): string
    {
        // Sanitize filename
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Add timestamp to avoid conflicts
        $timestamp = now()->format('Y-m-d_His');
        $uniqueName = "{$timestamp}_{$safeName}";
        
        return trim("{$this->incomingPrefix}{$userId}/{$uniqueName}", '/');
    }
    
    /**
     * Generate a path for the storage bucket
     * 
     * @param int $userId User ID
     * @param string $guid File GUID
     * @param string $fileType 'receipt' or 'document'
     * @param string $variant 'original', 'processed', etc.
     * @param string $extension File extension
     * @return string Generated path
     */
    protected function generateStoragePath(int $userId, string $guid, string $fileType, string $variant, string $extension): string
    {
        $typeFolder = $fileType === 'receipt' ? 'receipts' : 'documents';
        return trim("{$typeFolder}/{$userId}/{$guid}/{$variant}.{$extension}", '/');
    }
    
    /**
     * Check if using S3 storage
     * 
     * @return bool
     */
    public function isS3Storage(): bool
    {
        return config('filesystems.disks.paperpulse.driver') === 's3';
    }
    
    /**
     * Get the storage disk instance
     * 
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function getStorageDisk()
    {
        return $this->storageDisk;
    }
    
    /**
     * Get the incoming disk instance
     * 
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function getIncomingDisk()
    {
        return $this->incomingDisk;
    }
}