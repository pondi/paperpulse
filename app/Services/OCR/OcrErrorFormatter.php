<?php

namespace App\Services\OCR;

class OcrErrorFormatter
{
    public static function format(string $error, string $provider): string
    {
        if (str_contains($error, 'PDF format is not supported by Textract')) {
            return 'This PDF format is not supported by AWS Textract. The PDF may be password-protected, encrypted, XFA-based, or contain unsupported image formats (JPEG 2000). Please try converting it to a standard PDF format or use a different file.';
        }

        if (str_contains($error, 'UnsupportedDocumentException')) {
            return 'The uploaded file format is not supported by AWS Textract. Please ensure your PDF is not password-protected, encrypted, or XFA-based. Supported formats: PDF, PNG, JPG, TIFF.';
        }

        if (str_contains($error, 'InvalidParameterException')) {
            return 'Invalid file parameters. Please ensure the file is not corrupted and meets the size requirements (max 10MB for sync, 500MB for async operations).';
        }

        if (str_contains($error, 'exceeds') && str_contains($error, 'limit')) {
            return 'File size is too large. Please upload a file smaller than 10MB for synchronous processing or 500MB for asynchronous processing.';
        }

        if (str_contains($error, 'File does not exist')) {
            return 'The uploaded file could not be found. Please try uploading again.';
        }

        if (str_contains($error, 'File is empty')) {
            return 'The uploaded file is empty. Please upload a valid document.';
        }

        if (str_contains($error, 'password') || str_contains($error, 'encrypted')) {
            return 'The PDF file is password-protected or encrypted. Please remove the password protection and try again.';
        }

        if (str_contains($error, 'MIME type')) {
            return 'The file appears to have an incorrect file extension or corrupted content. Please verify the file and try again.';
        }

        if (str_contains($error, 'All OCR providers failed')) {
            return 'Document processing failed with all available OCR providers. This file format may not be supported, or the document may be corrupted. Please try converting the file to a standard PDF, PNG, or JPG format.';
        }

        $providerName = ucfirst($provider);
        if (str_contains($error, 'timeout')) {
            return "OCR processing timed out using {$providerName}. The file might be too complex or large. Please try a simpler file.";
        }

        return "{$providerName} OCR processing failed: {$error}";
    }
}

