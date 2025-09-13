<?php

namespace App\Services\Files;

class StoragePathBuilder
{
    public static function incomingPath(int $userId, string $filename, string $prefix = 'incoming/'): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $timestamp = now()->format('Y-m-d_His');
        $uniqueName = "{$timestamp}_{$safeName}";
        return trim("{$prefix}{$userId}/{$uniqueName}", '/');
    }

    public static function storagePath(int $userId, string $guid, string $fileType, string $variant, string $extension): string
    {
        $typeFolder = $fileType === 'receipt' ? 'receipts' : 'documents';
        return trim("{$typeFolder}/{$userId}/{$guid}/{$variant}.{$extension}", '/');
    }
}

