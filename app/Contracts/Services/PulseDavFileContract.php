<?php

namespace App\Contracts\Services;

use App\Models\PulseDavFile;
use App\Models\User;

interface PulseDavFileContract
{
    /**
     * Download file from S3
     */
    public function downloadFile(string $s3Path): string;

    /**
     * Delete file from S3 (soft delete with retention)
     */
    public function deleteFile(PulseDavFile $s3File): bool;

    /**
     * Process multiple files
     */
    public function processFiles(array $fileIds, User $user, string $fileType = 'receipt'): int;

    /**
     * Generate a temporary download URL for a file
     */
    public function getTemporaryUrl(PulseDavFile $s3File, int $expiration = 60): string;

    /**
     * Update file status
     */
    public function updateFileStatus(PulseDavFile $file, string $status, ?string $errorMessage = null): void;

    /**
     * Mark file as processing
     */
    public function markAsProcessing(PulseDavFile $file): void;

    /**
     * Mark file as completed
     */
    public function markAsCompleted(PulseDavFile $file, ?int $receiptId = null, ?int $documentId = null): void;

    /**
     * Mark file as failed
     */
    public function markAsFailed(PulseDavFile $file, string $errorMessage): void;

    /**
     * Check if file exists in S3
     */
    public function existsInS3(string $s3Path): bool;

    /**
     * Get file size in S3
     */
    public function getFileSize(string $s3Path): int;

    /**
     * Get file metadata
     */
    public function getFileMetadata(PulseDavFile $file): array;
}
