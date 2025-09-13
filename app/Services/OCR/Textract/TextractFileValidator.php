<?php

namespace App\Services\OCR\Textract;

use Illuminate\Support\Facades\Log;

class TextractFileValidator
{
    public static function validate(string $filePath, array $supportedExtensions): array
    {
        if (! file_exists($filePath)) {
            return ['valid' => false, 'error' => 'File does not exist'];
        }

        $fileSize = filesize($filePath);
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($fileSize > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit for Textract'];
        }
        if ($fileSize === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($extension, $supportedExtensions)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format '{$extension}'. Supported formats: ".implode(', ', $supportedExtensions),
            ];
        }

        $mimeType = mime_content_type($filePath) ?: '';
        $expectedMimeTypes = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'tiff' => ['image/tiff', 'image/tif'],
            'tif' => ['image/tiff', 'image/tif'],
        ];
        if (isset($expectedMimeTypes[$extension]) && ! in_array($mimeType, $expectedMimeTypes[$extension])) {
            return [
                'valid' => false,
                'error' => "File MIME type '{$mimeType}' doesn't match extension '{$extension}'. File may be corrupted or have wrong extension.",
            ];
        }

        if ($extension === 'pdf') {
            return self::validatePdf($filePath);
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'tiff', 'tif'])) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'Invalid or corrupted image file'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    public static function validatePdf(string $filePath): array
    {
        $handle = @fopen($filePath, 'rb');
        if (! $handle) {
            return ['valid' => false, 'error' => 'Cannot open PDF file'];
        }

        $header = fread($handle, 1024) ?: '';
        $fileSize = filesize($filePath) ?: 0;

        if (substr($header, 0, 4) !== '%PDF') {
            fclose($handle);

            return ['valid' => false, 'error' => 'Invalid PDF file - missing PDF header'];
        }

        if (preg_match('/%PDF-(\d+\.\d+)/', $header, $matches)) {
            Log::info('[Textract] PDF validation details', [
                'file_path' => basename($filePath),
                'pdf_version' => $matches[1],
                'file_size' => $fileSize,
            ]);
        }

        if (strpos($header, '/Encrypt') !== false) {
            fclose($handle);

            return ['valid' => false, 'error' => 'PDF file is encrypted - Textract cannot process encrypted PDFs'];
        }

        fseek($handle, max(0, $fileSize - 1024));
        $trailer = fread($handle, 1024) ?: '';
        fclose($handle);

        if (strpos($trailer, '%%EOF') === false) {
            Log::warning('[Textract] PDF may be corrupted - missing %%EOF marker', [
                'file_path' => basename($filePath),
                'trailer_sample' => substr($trailer, -100),
            ]);
        }

        $issues = [];
        if (strpos($header, '/AcroForm') !== false) {
            $issues[] = 'Contains AcroForms (interactive forms)';
        }
        if (strpos($header, '/Annot') !== false) {
            $issues[] = 'Contains annotations';
        }
        if (! empty($issues)) {
            Log::info('[Textract] PDF contains potentially problematic features', [
                'file_path' => basename($filePath),
                'issues' => $issues,
            ]);
        }

        return ['valid' => true, 'error' => null];
    }
}
