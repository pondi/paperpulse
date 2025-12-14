<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Log;

class DocumentUploadValidator
{
    public static function validate(UploadedFile $uploadedFile): array
    {
        $maxSize = 100 * 1024 * 1024; // 100MB (increased for office documents)
        if ($uploadedFile->getSize() > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 100MB limit'];
        }

        if ($uploadedFile->getSize() === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        // All supported formats (images, PDFs, office documents)
        $supportedExtensions = [
            'pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif',  // Images & PDFs
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', // MS Office
            'odt', 'ods', 'odp',                         // OpenDocument
            'rtf', 'txt', 'html', 'csv'                  // Other text formats
        ];

        if (! in_array($extension, $supportedExtensions)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format '{$extension}'. Supported formats: ".implode(', ', $supportedExtensions),
            ];
        }

        $mimeType = $uploadedFile->getMimeType();
        $expectedMimeTypes = [
            // Images & PDFs
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'tiff' => ['image/tiff'],
            'tif' => ['image/tiff'],

            // MS Office (legacy)
            'doc' => ['application/msword'],
            'xls' => ['application/vnd.ms-excel'],
            'ppt' => ['application/vnd.ms-powerpoint'],

            // MS Office (modern)
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/octet-stream' // Some servers don't recognize .docx MIME
            ],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/octet-stream'
            ],
            'pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/octet-stream'
            ],

            // OpenDocument formats
            'odt' => ['application/vnd.oasis.opendocument.text', 'application/octet-stream'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/octet-stream'],
            'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/octet-stream'],

            // Other text formats
            'rtf' => ['application/rtf', 'text/rtf'],
            'txt' => ['text/plain'],
            'html' => ['text/html'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
        ];

        // Only validate MIME type if we have expectations for this extension
        if (isset($expectedMimeTypes[$extension]) && ! in_array($mimeType, $expectedMimeTypes[$extension])) {
            // Log warning but don't fail - MIME type detection is unreliable for office files
            Log::warning("DocumentUploadValidator: MIME type mismatch", [
                'filename' => $uploadedFile->getClientOriginalName(),
                'extension' => $extension,
                'expected_mime' => $expectedMimeTypes[$extension],
                'actual_mime' => $mimeType,
            ]);

            // Only fail for images and PDFs where MIME detection is reliable
            if (in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif'])) {
                return [
                    'valid' => false,
                    'error' => "File MIME type '{$mimeType}' doesn't match extension '{$extension}'. File may be corrupted or have wrong extension.",
                ];
            }
        }

        // Deep validation for images and PDFs only (office docs will be validated by Gotenberg)
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
        // Office documents: skip deep validation, let Gotenberg handle it

        return ['valid' => true, 'error' => null];
    }
}
