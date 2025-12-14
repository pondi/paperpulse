<?php

namespace App\Services\Workers;

use App\Services\File\FileStorageService;
use App\Services\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Manages file lifecycle for worker jobs in distributed environments.
 *
 * In production, web servers and worker servers may be separate containers.
 * This service ensures workers can access files by downloading from S3 (the
 * single source of truth) and properly cleaning up local files after processing.
 *
 * Key principles:
 * - S3 is the single source of truth for all files
 * - Local files are temporary working copies
 * - Always clean up local files after processing (success or failure)
 * - Never delete files from S3 (permanent storage)
 */
class WorkerFileManager
{
    protected StorageService $storageService;

    protected FileStorageService $fileStorageService;

    public function __construct(
        StorageService $storageService,
        FileStorageService $fileStorageService
    ) {
        $this->storageService = $storageService;
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Ensure file is available locally for processing.
     * Downloads from S3 if not present locally.
     *
     * @param  string  $s3Path  S3 path to the file
     * @param  string  $fileGuid  Unique file identifier
     * @param  string  $extension  File extension
     * @param  string|null  $localPath  Optional existing local path to check first
     * @return string Local file path
     *
     * @throws Exception If file cannot be downloaded
     */
    public function ensureLocalFile(
        string $s3Path,
        string $fileGuid,
        string $extension,
        ?string $localPath = null
    ): string {
        // Check if local file already exists and is valid
        if ($localPath && file_exists($localPath) && filesize($localPath) > 0) {
            Log::debug('[WorkerFileManager] Using existing local file', [
                'file_guid' => $fileGuid,
                'local_path' => $localPath,
                'file_size' => filesize($localPath),
            ]);

            return $localPath;
        }

        // Download from S3
        Log::info('[WorkerFileManager] Downloading file from S3 for processing', [
            'file_guid' => $fileGuid,
            's3_path' => $s3Path,
            'extension' => $extension,
        ]);

        try {
            $fileContent = $this->storageService->getFile($s3Path);

            if (! $fileContent) {
                throw new Exception("File not found in S3: {$s3Path}");
            }

            // Store locally for processing
            $localPath = $this->fileStorageService->storeWorkingContent(
                $fileContent,
                $fileGuid,
                $extension
            );

            Log::info('[WorkerFileManager] File downloaded from S3 successfully', [
                'file_guid' => $fileGuid,
                's3_path' => $s3Path,
                'local_path' => $localPath,
                'file_size' => strlen($fileContent),
            ]);

            return $localPath;

        } catch (Exception $e) {
            Log::error('[WorkerFileManager] Failed to download file from S3', [
                'file_guid' => $fileGuid,
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("The uploaded file could not be found. Please try uploading again. (Error: {$e->getMessage()})");
        }
    }

    /**
     * Clean up local working file after processing.
     * Safe to call even if file doesn't exist.
     *
     * @param  string  $localPath  Local file path to clean up
     * @param  string  $fileGuid  File GUID for logging
     * @param  string  $context  Context for logging (e.g., 'ProcessReceipt', 'ProcessDocument')
     */
    public function cleanupLocalFile(string $localPath, string $fileGuid, string $context = 'Worker'): void
    {
        try {
            if (! file_exists($localPath)) {
                Log::debug("[WorkerFileManager] [{$context}] Local file already removed", [
                    'file_guid' => $fileGuid,
                    'local_path' => $localPath,
                ]);

                return;
            }

            $fileSize = filesize($localPath);
            $deleted = $this->fileStorageService->deleteWorkingFile($localPath);

            if ($deleted) {
                Log::info("[WorkerFileManager] [{$context}] Local file cleaned up", [
                    'file_guid' => $fileGuid,
                    'local_path' => $localPath,
                    'file_size' => $fileSize,
                ]);
            }

        } catch (Exception $e) {
            // Non-critical error - log but don't throw
            Log::warning("[WorkerFileManager] [{$context}] Failed to clean up local file", [
                'file_guid' => $fileGuid,
                'local_path' => $localPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up multiple local files.
     *
     * @param  array  $localPaths  Array of local file paths
     * @param  string  $fileGuid  File GUID for logging
     * @param  string  $context  Context for logging
     */
    public function cleanupLocalFiles(array $localPaths, string $fileGuid, string $context = 'Worker'): void
    {
        foreach ($localPaths as $localPath) {
            if ($localPath) {
                $this->cleanupLocalFile($localPath, $fileGuid, $context);
            }
        }
    }

    /**
     * Execute a processing callback with automatic cleanup.
     * Ensures local file is cleaned up even if processing fails.
     *
     * @param  string  $s3Path  S3 path to the file
     * @param  string  $fileGuid  File GUID
     * @param  string  $extension  File extension
     * @param  callable  $callback  Processing callback that receives the local file path
     * @param  string  $context  Context for logging
     * @return mixed Result from callback
     *
     * @throws Exception If download or processing fails
     */
    public function processWithCleanup(
        string $s3Path,
        string $fileGuid,
        string $extension,
        callable $callback,
        string $context = 'Worker'
    ) {
        $localPath = null;

        try {
            // Ensure file is available locally
            $localPath = $this->ensureLocalFile($s3Path, $fileGuid, $extension);

            // Execute processing callback
            $result = $callback($localPath);

            return $result;

        } finally {
            // Always clean up, even if processing failed
            if ($localPath) {
                $this->cleanupLocalFile($localPath, $fileGuid, $context);
            }
        }
    }
}
