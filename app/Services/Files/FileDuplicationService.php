<?php

namespace App\Services\Files;

use App\Contracts\Services\FileDuplicationContract;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileDuplicationService implements FileDuplicationContract
{
    /**
     * Calculate SHA-256 hash of file content.
     *
     * @param  string  $content  Raw file content
     * @return string SHA-256 hash in hexadecimal format
     */
    public function calculateHash(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Check if a duplicate file exists for the given user.
     *
     * @param  string  $hash  SHA-256 hash to check
     * @param  int  $userId  User ID to scope the search
     * @return File|null Returns the existing File model if duplicate found, null otherwise
     */
    public function findDuplicateByHash(string $hash, int $userId): ?File
    {
        return File::where('user_id', $userId)
            ->where('file_hash', $hash)
            ->whereNotNull('file_hash')
            // If a file is marked completed but has no linked receipt/document,
            // treat it as non-existent for deduplication purposes (e.g. after deletion).
            ->where(function ($query) {
                $query->where('status', '!=', 'completed')
                    ->orWhereHas('receipts')
                    ->orWhereHas('documents');
            })
            ->first();
    }

    /**
     * Check if file content is a duplicate and return details.
     *
     * @param  string  $content  Raw file content
     * @param  int  $userId  User ID to scope the search
     * @return array{isDuplicate: bool, hash: string, existingFile: File|null}
     */
    public function checkDuplication(string $content, int $userId): array
    {
        $hash = $this->calculateHash($content);
        $existingFile = $this->findDuplicateByHash($hash, $userId);

        return [
            'isDuplicate' => $existingFile !== null,
            'hash' => $hash,
            'existingFile' => $existingFile,
        ];
    }

    /**
     * Clean up uploaded file that was identified as a duplicate.
     * Deletes from S3 if path provided, or from local filesystem.
     *
     * @param  string|null  $s3Path  S3 path to delete (optional)
     * @param  string|null  $localPath  Local file path to delete (optional)
     * @return bool True if cleanup successful
     */
    public function cleanupDuplicateFile(?string $s3Path = null, ?string $localPath = null): bool
    {
        $cleanupSuccessful = true;

        // Clean up S3 file if path provided
        if ($s3Path !== null) {
            try {
                if (Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                    Log::info('Deleted duplicate file from S3', ['path' => $s3Path]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete duplicate file from S3', [
                    'path' => $s3Path,
                    'error' => $e->getMessage(),
                ]);
                $cleanupSuccessful = false;
            }
        }

        // Clean up local file if path provided
        if ($localPath !== null) {
            try {
                if (file_exists($localPath) && is_file($localPath)) {
                    unlink($localPath);
                    Log::info('Deleted duplicate file from local filesystem', ['path' => $localPath]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete duplicate file from local filesystem', [
                    'path' => $localPath,
                    'error' => $e->getMessage(),
                ]);
                $cleanupSuccessful = false;
            }
        }

        return $cleanupSuccessful;
    }
}
