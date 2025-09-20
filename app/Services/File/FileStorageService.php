<?php

namespace App\Services\File;

use App\Contracts\Services\FileStorageContract;
use App\Services\S3StorageService;
use App\Services\StorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService implements FileStorageContract
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Store uploaded file locally for processing
     */
    public function storeWorkingFile(UploadedFile $uploadedFile, string $fileGuid): string
    {
        try {
            $fileName = $fileGuid.'.'.$uploadedFile->getClientOriginalExtension();
            $storedFile = $uploadedFile->storeAs('uploads', $fileName, 'local');

            Log::debug('[FileStorageService] Working file stored', [
                'file_path' => $storedFile,
                'file_guid' => $fileGuid,
            ]);

            return Storage::disk('local')->path($storedFile);
        } catch (Exception $e) {
            Log::error('[FileStorageService] Working file storage failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            throw $e;
        }
    }

    /**
     * Store file content locally for processing
     */
    public function storeWorkingContent(string $content, string $fileGuid, string $extension): string
    {
        try {
            $fileName = $fileGuid.'.'.$extension;
            $path = 'uploads/'.$fileName;

            Storage::disk('local')->put($path, $content);

            Log::debug('[FileStorageService] Working content stored', [
                'file_path' => $path,
                'file_guid' => $fileGuid,
            ]);

            return Storage::disk('local')->path($path);
        } catch (Exception $e) {
            Log::error('[FileStorageService] Working content storage failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            throw $e;
        }
    }

    /**
     * Store file to S3 storage bucket
     */
    public function storeToS3(string $content, int $userId, string $fileGuid, string $fileType, string $variant, string $extension): string
    {
        return $this->storageService->storeFile(
            $content,
            $userId,
            $fileGuid,
            $fileType,
            $variant,
            $extension
        );
    }

    /**
     * Store uploaded file to S3 storage bucket
     */
    public function storeUploadedFileToS3(UploadedFile $uploadedFile, int $userId, string $fileGuid, string $fileType, string $variant): string
    {
        $content = file_get_contents($uploadedFile->getRealPath());
        $extension = $uploadedFile->getClientOriginalExtension();

        return $this->storeToS3($content, $userId, $fileGuid, $fileType, $variant, $extension);
    }

    /**
     * Delete working file from local storage
     */
    public function deleteWorkingFile(string $filePath): bool
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);

                Log::debug('[FileStorageService] Working file deleted', [
                    'file_path' => $filePath,
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('[FileStorageService] Working file deletion failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return false;
        }
    }

    /**
     * Check if file exists in S3
     */
    public function existsInS3(string $disk, string $path): bool
    {
        return S3StorageService::exists($disk, $path);
    }

    /**
     * Get file content from S3
     */
    public function getFromS3(string $disk, string $path): string
    {
        return S3StorageService::get($disk, $path);
    }

    /**
     * Get file size from S3
     */
    public function getSizeFromS3(string $disk, string $path): int
    {
        return S3StorageService::size($disk, $path);
    }

    /**
     * Delete file from S3
     */
    public function deleteFromS3(string $disk, string $path): void
    {
        S3StorageService::delete($disk, $path);
    }

    /**
     * Generate unique file GUID
     */
    public function generateFileGuid(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Clean up temporary files older than specified hours
     */
    public function cleanupOldWorkingFiles(int $hoursOld = 24): int
    {
        try {
            $uploadsPath = Storage::disk('local')->path('uploads');
            if (! is_dir($uploadsPath)) {
                return 0;
            }

            $files = glob($uploadsPath.'/*');
            $cutoffTime = time() - ($hoursOld * 3600);
            $deletedCount = 0;

            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $deletedCount++;
                }
            }

            Log::info('[FileStorageService] Cleanup completed', [
                'deleted_files' => $deletedCount,
                'hours_old' => $hoursOld,
            ]);

            return $deletedCount;
        } catch (Exception $e) {
            Log::error('[FileStorageService] Cleanup failed', [
                'error' => $e->getMessage(),
                'hours_old' => $hoursOld,
            ]);

            return 0;
        }
    }
}
