<?php

namespace App\Contracts\Services;

use App\Models\File;

interface FileDuplicationContract
{
    /**
     * Calculate SHA-256 hash of file content.
     *
     * @param  string  $content  Raw file content
     * @return string SHA-256 hash in hexadecimal format
     */
    public function calculateHash(string $content): string;

    /**
     * Check if a duplicate file exists for the given user.
     *
     * @param  string  $hash  SHA-256 hash to check
     * @param  int  $userId  User ID to scope the search
     * @return File|null Returns the existing File model if duplicate found, null otherwise
     */
    public function findDuplicateByHash(string $hash, int $userId): ?File;

    /**
     * Check if file content is a duplicate and return details.
     *
     * @param  string  $content  Raw file content
     * @param  int  $userId  User ID to scope the search
     * @return array{isDuplicate: bool, hash: string, existingFile: File|null}
     */
    public function checkDuplication(string $content, int $userId): array;

    /**
     * Clean up uploaded file that was identified as a duplicate.
     * Deletes from S3 if path provided, or from local filesystem.
     *
     * @param  string|null  $s3Path  S3 path to delete (optional)
     * @param  string|null  $localPath  Local file path to delete (optional)
     * @return bool True if cleanup successful
     */
    public function cleanupDuplicateFile(?string $s3Path = null, ?string $localPath = null): bool;
}
