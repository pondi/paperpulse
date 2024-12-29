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

            Log::info('ConversionService - PDF converted to image', [
                'storedFilePath' => $storedFilePath,
                'guid' => $fileGUID,
                'outputPath' => $outputPath
            ]);

            // Store image in permanent storage
            $imageContent = file_get_contents($outputPath);
            $documentService->storeDocument(
                $imageContent,
                $fileGUID,
                'receipts',
                'jpg'
            );

            Log::info('ConversionService - Image stored', [
                'guid' => $fileGUID,
                'storage_driver' => config('filesystems.disks.documents.driver')
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error converting PDF to image: ' . $e->getMessage(), [
                'guid' => $fileGUID,
                'path' => $storedFilePath,
                'exception' => $e
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