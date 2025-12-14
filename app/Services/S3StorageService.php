<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;

class S3StorageService
{
    /**
     * Get file content, handling aws-chunked encoding if necessary
     */
    public static function get(string $disk, string $path): string
    {
        try {
            // First try normal read through Storage facade
            return Storage::disk($disk)->get($path);
        } catch (UnableToReadFile $e) {
            // Log the actual error message for debugging
            Log::debug('[S3StorageService] Read failed', [
                'error' => $e->getMessage(),
                'disk' => $disk,
                'path' => $path,
            ]);

            // Check if it's the chunked encoding error
            $errorMessage = $e->getMessage();
            if ($e->getPrevious()) {
                $errorMessage .= ' '.$e->getPrevious()->getMessage();
            }

            if (str_contains($errorMessage, 'Unrecognized content encoding type') ||
                str_contains($errorMessage, 'cURL error 61')) {
                Log::warning('[S3StorageService] Detected aws-chunked encoding, using fallback', [
                    'disk' => $disk,
                    'path' => $path,
                ]);

                return self::getWithChunkedFallback($disk, $path);
            }

            throw $e;
        }
    }

    /**
     * Get file using direct S3 client to bypass encoding issues
     */
    private static function getWithChunkedFallback(string $disk, string $path): string
    {
        $config = config("filesystems.disks.{$disk}");

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        try {
            // First check if file exists and has chunked encoding
            $headResult = $s3Client->headObject([
                'Bucket' => $config['bucket'],
                'Key' => $path,
            ]);

            Log::info('[S3StorageService] Head object result', [
                'ContentEncoding' => $headResult['ContentEncoding'] ?? 'none',
                'ContentType' => $headResult['ContentType'] ?? 'unknown',
            ]);

            if (isset($headResult['ContentEncoding']) &&
                (str_contains($headResult['ContentEncoding'], 'aws-chunked') ||
                 $headResult['ContentEncoding'] === 'aws-chunked')) {

                Log::info('[S3StorageService] Fixing aws-chunked encoding', [
                    'current_encoding' => $headResult['ContentEncoding'],
                ]);

                // Copy the object to itself without the encoding
                $s3Client->copyObject([
                    'Bucket' => $config['bucket'],
                    'CopySource' => "{$config['bucket']}/{$path}",
                    'Key' => $path,
                    'MetadataDirective' => 'REPLACE',
                    'ContentType' => $headResult['ContentType'] ?? 'application/octet-stream',
                    'Metadata' => $headResult['Metadata'] ?? [],
                ]);

                Log::info('[S3StorageService] Fixed aws-chunked encoding on file', [
                    'disk' => $disk,
                    'path' => $path,
                ]);
            }

            // Now read the file normally
            return Storage::disk($disk)->get($path);

        } catch (Exception $e) {
            Log::error('[S3StorageService] Failed to handle chunked encoding', [
                'error' => $e->getMessage(),
                'disk' => $disk,
                'path' => $path,
            ]);

            throw new UnableToReadFile("Unable to read file: {$path}", 0, $e);
        }
    }

    /**
     * Check if file exists
     */
    public static function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Get file size
     */
    public static function size(string $disk, string $path): int
    {
        return Storage::disk($disk)->size($path);
    }

    /**
     * Delete file
     */
    public static function delete(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    /**
     * Put file content
     */
    public static function put(string $disk, string $path, string $content): bool
    {
        return Storage::disk($disk)->put($path, $content);
    }
}
