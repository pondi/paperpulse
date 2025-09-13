<?php

namespace App\Services\Files;

/**
 * Pure helpers that construct normalized storage paths.
 */
class StoragePathBuilder
{
    /**
     * Build an incoming bucket path scoped by user and timestamp.
     */
    public static function incomingPath(int $userId, string $filename, string $prefix = 'incoming/'): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $timestamp = now()->format('Y-m-d_His');
        $uniqueName = "{$timestamp}_{$safeName}";

        return trim("{$prefix}{$userId}/{$uniqueName}", '/');
    }

    /**
     * Build the canonical storage path for a file variant.
     *
     * @param  string  $fileType  'receipt' or 'document'
     * @param  string  $variant  e.g. 'original', 'ocr_text'
     */
    public static function storagePath(int $userId, string $guid, string $fileType, string $variant, string $extension): string
    {
        $typeFolder = $fileType === 'receipt' ? 'receipts' : 'documents';

        return trim("{$typeFolder}/{$userId}/{$guid}/{$variant}.{$extension}", '/');
    }
}
