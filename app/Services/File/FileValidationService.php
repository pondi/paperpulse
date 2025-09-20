<?php

namespace App\Services\File;

use App\Contracts\Services\FileValidationContract;
use Illuminate\Http\UploadedFile;

class FileValidationService implements FileValidationContract
{
    /**
     * Validate file type is supported
     */
    public function isSupported(string $extension, string $fileType): bool
    {
        $extension = strtolower($extension);

        if ($fileType === 'receipt') {
            $supported = config('processing.documents.supported_formats.receipts');
        } else {
            $supported = config('processing.documents.supported_formats.documents');
        }

        return in_array($extension, $supported);
    }

    /**
     * Get maximum file size for a file type
     */
    public function getMaxFileSize(string $fileType): int
    {
        if ($fileType === 'receipt') {
            return config('processing.documents.max_file_size.receipts') * 1024 * 1024; // MB to bytes
        } else {
            return config('processing.documents.max_file_size.documents') * 1024 * 1024; // MB to bytes
        }
    }

    /**
     * Validate uploaded file
     */
    public function validateUploadedFile(UploadedFile $file, string $fileType): array
    {
        $errors = [];

        // Check file extension
        $extension = $file->getClientOriginalExtension();
        if (! $this->isSupported($extension, $fileType)) {
            $errors[] = "File type '{$extension}' is not supported for {$fileType}s";
        }

        // Check file size
        $maxSize = $this->getMaxFileSize($fileType);
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            $errors[] = "File size exceeds maximum limit of {$maxSizeMB}MB for {$fileType}s";
        }

        // Basic MIME type validation
        $mimeType = $file->getClientMimeType();
        if (! $this->isValidMimeType($mimeType, $extension)) {
            $errors[] = "File MIME type '{$mimeType}' does not match extension '{$extension}'";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate file data array
     */
    public function validateFileData(array $fileData, string $fileType): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['fileName', 'extension', 'size', 'content'];
        foreach ($requiredFields as $field) {
            if (! isset($fileData[$field]) || empty($fileData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if (! empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file extension
        $extension = $fileData['extension'];
        if (! $this->isSupported($extension, $fileType)) {
            $errors[] = "File type '{$extension}' is not supported for {$fileType}s";
        }

        // Check file size
        $maxSize = $this->getMaxFileSize($fileType);
        if ($fileData['size'] > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            $errors[] = "File size exceeds maximum limit of {$maxSizeMB}MB for {$fileType}s";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if MIME type matches file extension
     */
    protected function isValidMimeType(string $mimeType, string $extension): bool
    {
        $validMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'application/csv'],
        ];

        $extension = strtolower($extension);
        if (! isset($validMimeTypes[$extension])) {
            return true; // Allow unknown extensions for now
        }

        return in_array($mimeType, $validMimeTypes[$extension]);
    }

    /**
     * Get MIME type from file extension
     */
    public function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
