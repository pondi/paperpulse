<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;

class DocumentUploadValidator
{
    public static function validate(UploadedFile $uploadedFile): array
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($uploadedFile->getSize() > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        if ($uploadedFile->getSize() === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $supportedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif'];
        if (!in_array($extension, $supportedExtensions)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format '{$extension}'. Supported formats: ".implode(', ', $supportedExtensions),
            ];
        }

        $mimeType = $uploadedFile->getMimeType();
        $expectedMimeTypes = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'tiff' => ['image/tiff'],
            'tif' => ['image/tiff'],
        ];
        if (isset($expectedMimeTypes[$extension]) && !in_array($mimeType, $expectedMimeTypes[$extension])) {
            return [
                'valid' => false,
                'error' => "File MIME type '{$mimeType}' doesn't match extension '{$extension}'. File may be corrupted or have wrong extension.",
            ];
        }

        $tempPath = $uploadedFile->getPathname();
        if ($extension === 'pdf') {
            $handle = @fopen($tempPath, 'rb');
            if ($handle) {
                $header = fread($handle, 5) ?: '';
                fclose($handle);
                if (substr($header, 0, 4) !== '%PDF') {
                    return ['valid' => false, 'error' => 'Invalid PDF file - missing PDF header'];
                }
            }
        } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'tiff', 'tif'])) {
            $imageInfo = @getimagesize($tempPath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'Invalid or corrupted image file'];
            }
        }

        return ['valid' => true, 'error' => null];
    }
}

