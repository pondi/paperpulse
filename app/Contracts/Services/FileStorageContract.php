<?php

namespace App\Contracts\Services;

use Illuminate\Http\UploadedFile;

interface FileStorageContract
{
    /**
     * Store uploaded file locally for processing
     */
    public function storeWorkingFile(UploadedFile $uploadedFile, string $fileGuid): string;

    /**
     * Store file content locally for processing
     */
    public function storeWorkingContent(string $content, string $fileGuid, string $extension): string;

    /**
     * Store file to S3 storage bucket
     */
    public function storeToS3(string $content, int $userId, string $fileGuid, string $fileType, string $variant, string $extension): string;

    /**
     * Store uploaded file to S3 storage bucket
     */
    public function storeUploadedFileToS3(UploadedFile $uploadedFile, int $userId, string $fileGuid, string $fileType, string $variant): string;

    /**
     * Delete working file from local storage
     */
    public function deleteWorkingFile(string $filePath): bool;

    /**
     * Check if file exists in S3
     */
    public function existsInS3(string $disk, string $path): bool;

    /**
     * Get file content from S3
     */
    public function getFromS3(string $disk, string $path): string;

    /**
     * Get file size from S3
     */
    public function getSizeFromS3(string $disk, string $path): int;

    /**
     * Delete file from S3
     */
    public function deleteFromS3(string $disk, string $path): void;

    /**
     * Generate unique file GUID
     */
    public function generateFileGuid(): string;

    /**
     * Clean up temporary files older than specified hours
     */
    public function cleanupOldWorkingFiles(int $hoursOld = 24): int;
}
