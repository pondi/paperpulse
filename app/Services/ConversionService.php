<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;

class ConversionService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Convert PDF to JPG image for receipt processing
     */
    public function pdfToImage(string $storedFilePath, string $fileGUID, DocumentService $documentService): bool
    {
        try {
            // Check if imagick extension is available
            if (! extension_loaded('imagick')) {
                Log::warning('(ConversionService) [pdfToImage] - Imagick extension not available, skipping PDF to image conversion', [
                    'file_guid' => $fileGUID,
                ]);

                // For now, we'll skip the conversion but still return true to continue processing
                // The OCR service should be able to handle PDFs directly
                return true;
            }

            // Check if Ghostscript is available
            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::warning('(ConversionService) [pdfToImage] - Ghostscript not available, skipping PDF to image conversion', [
                    'file_guid' => $fileGUID,
                ]);

                // Skip conversion but continue processing
                return true;
            }

            $spatiePDF = new Pdf($storedFilePath);
            $outputPath = storage_path('app/uploads/'.$fileGUID.'.jpg');

            // Generate the image
            $spatiePDF->quality(85)
                ->resolution(144)
                ->save($outputPath);

            Log::debug('(ConversionService) [pdfToImage] - PDF converted to image', [
                'file_path' => $storedFilePath,
                'file_guid' => $fileGUID,
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

            // Clean up temporary file
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("(ConversionService) [pdfToImage] - Error converting PDF to image (file: {$fileGUID})", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
