<?php

namespace App\Contracts\Services;

use App\Models\PulseDavFile;
use App\Models\User;

interface PulseDavSyncContract
{
    /**
     * List all PulseDav files for a specific user from S3
     */
    public function listUserFiles(User $user): array;

    /**
     * Sync S3 files to database
     */
    public function syncS3Files(User $user): int;

    /**
     * List all PulseDav files and folders for a specific user from S3
     */
    public function listUserFilesWithFolders(User $user): array;

    /**
     * Sync S3 files with folder support
     */
    public function syncS3FilesWithFolders(User $user): int;

    /**
     * Get processing status for a file
     */
    public function getProcessingStatus(PulseDavFile $s3File): array;
}
