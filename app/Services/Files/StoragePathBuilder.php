<?php

namespace App\Services\Files;

use App\Models\File;

/**
 * Helpers for canonical paths and recorded file variants.
 */
class StoragePathBuilder
{
    public static function variantPath(File $file, string $variant): ?string
    {
        return match ($variant) {
            'original' => $file->s3_original_path ?: null,
            'preview' => $file->has_image_preview ? ($file->s3_image_path ?: null) : null,
            'archive' => $file->s3_archive_path ?: (strtolower((string) $file->fileExtension) === 'pdf' ? ($file->s3_original_path ?: null) : null),
            default => null,
        };
    }

    public static function pdfVariant(File $file): ?string
    {
        if ($file->s3_archive_path) {
            return 'archive';
        }

        return strtolower((string) $file->fileExtension) === 'pdf' && $file->s3_original_path ? 'original' : null;
    }

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
     * @param  string  $variant  e.g. 'original', 'ocr_text', 'preview'
     */
    public static function storagePath(int $userId, string $guid, string $fileType, string $variant, string $extension): string
    {
        $typeFolder = $fileType === 'receipt' ? 'receipts' : 'documents';

        return trim("{$typeFolder}/{$userId}/{$guid}/{$variant}.{$extension}", '/');
    }

    /**
     * Build storage path for image preview.
     *
     * @param  string  $fileType  'receipt' or 'document'
     */
    public static function previewPath(int $userId, string $guid, string $fileType): string
    {
        return self::storagePath($userId, $guid, $fileType, 'preview', 'jpg');
    }
}
