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
        // Validate input file
        if (! file_exists($storedFilePath)) {
            Log::error('(ConversionService) [pdfToImage] - Source PDF file not found', [
                'file_guid' => $fileGUID,
                'file_path' => $storedFilePath,
            ]);

            return false;
        }

        // Check file size (limit to reasonable size)
        $fileSize = filesize($storedFilePath);
        $maxSize = 50 * 1024 * 1024; // 50MB limit
        if ($fileSize > $maxSize) {
            Log::warning('(ConversionService) [pdfToImage] - PDF file too large for conversion', [
                'file_guid' => $fileGUID,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'max_size_mb' => $maxSize / 1024 / 1024,
            ]);

            return false;
        }

        try {
            // Check if imagick extension is available
            if (! extension_loaded('imagick')) {
                Log::warning('(ConversionService) [pdfToImage] - Imagick extension not available, skipping PDF to image conversion', [
                    'file_guid' => $fileGUID,
                ]);

                return false; // Changed to false to indicate actual failure
            }

            // Check if Ghostscript is available
            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::warning('(ConversionService) [pdfToImage] - Ghostscript not available, skipping PDF to image conversion', [
                    'file_guid' => $fileGUID,
                ]);

                return false; // Changed to false to indicate actual failure
            }

            $outputPath = storage_path('app/uploads/'.$fileGUID.'.jpg');

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $spatiePDF = new Pdf($storedFilePath);

            // Configure conversion settings
            $spatiePDF->quality(85)
                ->resolution(144)
                ->save($outputPath);

            // Verify output file was created
            if (! file_exists($outputPath)) {
                throw new \Exception('PDF conversion completed but output file was not created');
            }

            $outputSize = filesize($outputPath);
            if ($outputSize === 0) {
                throw new \Exception('PDF conversion produced empty output file');
            }

            Log::info('(ConversionService) [pdfToImage] - PDF converted to image successfully', [
                'file_path' => $storedFilePath,
                'file_guid' => $fileGUID,
                'input_size_kb' => round($fileSize / 1024, 2),
                'output_size_kb' => round($outputSize / 1024, 2),
                'output_path' => $outputPath,
            ]);

            // Store image in permanent storage
            $imageContent = file_get_contents($outputPath);
            $documentService->storeDocument(
                $imageContent,
                $fileGUID,
                'receipts',
                'jpg'
            );

            Log::info('(ConversionService) [pdfToImage] - Image stored permanently', [
                'file_guid' => $fileGUID,
                'storage_size_kb' => round(strlen($imageContent) / 1024, 2),
            ]);

            // Clean up temporary file
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return true;

        } catch (\Spatie\PdfToImage\Exceptions\InvalidFormat $e) {
            Log::error('(ConversionService) [pdfToImage] - Invalid PDF format', [
                'file_guid' => $fileGUID,
                'error' => $e->getMessage(),
                'file_path' => $storedFilePath,
            ]);

            return false;
        } catch (\Spatie\PdfToImage\Exceptions\PageDoesNotExist $e) {
            Log::error('(ConversionService) [pdfToImage] - PDF page does not exist', [
                'file_guid' => $fileGUID,
                'error' => $e->getMessage(),
                'file_path' => $storedFilePath,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('(ConversionService) [pdfToImage] - Error converting PDF to image', [
                'file_guid' => $fileGUID,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file_path' => $storedFilePath,
            ]);

            // Clean up temporary file if it exists
            $outputPath = storage_path('app/uploads/'.$fileGUID.'.jpg');
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return false;
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
