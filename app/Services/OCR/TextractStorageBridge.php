<?php

namespace App\Services\OCR;

use App\Services\File\FileStorageService;
use App\Services\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles cross-storage transfers for Textract processing.
 *
 * Textract requires files to be in AWS S3, but our permanent storage is in DigitalOcean Spaces.
 * This bridge downloads from DigitalOcean, uploads to AWS Textract bucket for processing,
 * then cleans up the temporary AWS files.
 */
class TextractStorageBridge
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
     * Transfer file from DigitalOcean to AWS Textract bucket.
     *
     * @param string $digitalOceanPath S3 path in DigitalOcean ('paperpulse' disk)
     * @param string $fileGuid Unique file identifier
     * @param string $extension File extension
     * @return array ['textract_path' => string, 'local_path' => string]
     * @throws Exception
     */
    public function transferToTextractBucket(
        string $digitalOceanPath,
        string $fileGuid,
        string $extension
    ): array {
        Log::info('[TextractBridge] Starting transfer to Textract bucket', [
            'file_guid' => $fileGuid,
            'source_path' => $digitalOceanPath,
        ]);

        // 1. Download from DigitalOcean Spaces
        $fileContent = $this->storageService->getFile($digitalOceanPath);

        if (!$fileContent) {
            throw new Exception("Failed to download file from DigitalOcean: {$digitalOceanPath}");
        }

        Log::debug('[TextractBridge] Downloaded from DigitalOcean', [
            'file_guid' => $fileGuid,
            'file_size' => strlen($fileContent),
        ]);

        // 2. Store locally (temporary)
        $localPath = $this->fileStorageService->storeWorkingContent(
            $fileContent,
            $fileGuid,
            $extension
        );

        // 3. Upload to AWS Textract bucket
        $textractPath = "temp/{$fileGuid}/" . basename($localPath);

        try {
            Storage::disk('textract')->put($textractPath, $fileContent);

            Log::info('[TextractBridge] Uploaded to Textract bucket', [
                'file_guid' => $fileGuid,
                'textract_path' => $textractPath,
                'file_size' => strlen($fileContent),
            ]);

            return [
                'textract_path' => $textractPath,
                'local_path' => $localPath,
            ];

        } catch (Exception $e) {
            // Clean up local file if upload fails
            $this->fileStorageService->deleteWorkingFile($localPath);

            throw new Exception("Failed to upload to Textract bucket: {$e->getMessage()}");
        }
    }

    /**
     * Clean up files from Textract bucket and local temp storage.
     *
     * @param string $textractPath Path in Textract bucket
     * @param string|null $localPath Optional local temp path
     */
    public function cleanupFromTextractBucket(
        string $textractPath,
        ?string $localPath = null
    ): void {
        // Clean up from Textract bucket
        try {
            if (Storage::disk('textract')->exists($textractPath)) {
                Storage::disk('textract')->delete($textractPath);
                Log::debug('[TextractBridge] Cleaned up from Textract bucket', [
                    'path' => $textractPath,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('[TextractBridge] Failed to cleanup Textract bucket', [
                'path' => $textractPath,
                'error' => $e->getMessage(),
            ]);
        }

        // Clean up local temp file
        if ($localPath) {
            try {
                $this->fileStorageService->deleteWorkingFile($localPath);
                Log::debug('[TextractBridge] Cleaned up local temp file', [
                    'path' => $localPath,
                ]);
            } catch (Exception $e) {
                Log::warning('[TextractBridge] Failed to cleanup local file', [
                    'path' => $localPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
