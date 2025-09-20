<?php

namespace App\Contracts\Services;

use Illuminate\Http\UploadedFile;

interface FileValidationContract
{
    /**
     * Validate file type is supported
     */
    public function isSupported(string $extension, string $fileType): bool;

    /**
     * Get maximum file size for a file type
     */
    public function getMaxFileSize(string $fileType): int;

    /**
     * Validate uploaded file
     */
    public function validateUploadedFile(UploadedFile $file, string $fileType): array;

    /**
     * Validate file data array
     */
    public function validateFileData(array $fileData, string $fileType): array;

    /**
     * Get MIME type from file extension
     */
    public function getMimeType(string $extension): string;
}
