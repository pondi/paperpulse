<?php

namespace App\Services;

use Spatie\PdfToImage\Pdf;
use Illuminate\Support\Facades\Log;

class ConversionService
{
    /**
     * Convert PDF to JPG image
     */
    public function pdfToImage(string $storedFilePath, string $fileGUID, DocumentService $documentService): bool
    {
        try {
            $spatiePDF = new Pdf($storedFilePath);
            $outputPath = storage_path('app/uploads/' . $fileGUID . '.jpg');

            // Generate the image
            $spatiePDF->quality(85)
                ->size(400)
                ->save($outputPath);

            Log::debug("(ConversionService) [pdfToImage] - PDF converted to image", [
                'file_path' => $storedFilePath,
                'file_guid' => $fileGUID
            ]);

            // Store image in permanent storage
            $imageContent = file_get_contents($outputPath);
            $documentService->storeDocument(
                $imageContent,
                $fileGUID,
                'receipts',
                'jpg'
            );

            Log::debug("(ConversionService) [pdfToImage] - Image stored (file: {$fileGUID})");

            return true;
        } catch (\Exception $e) {
            Log::error("(ConversionService) [pdfToImage] - Error converting PDF to image (file: {$fileGUID})", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get the content type based on file extension
     */
    public function getContentType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
} 