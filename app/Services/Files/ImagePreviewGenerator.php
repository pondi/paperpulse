<?php

namespace App\Services\Files;

use Exception;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Generates image previews from PDF files and images.
 * Single responsibility: Convert files to optimized JPG previews.
 */
class ImagePreviewGenerator
{
    /**
     * Generate a JPG preview from any supported file type.
     * Routes to appropriate handler based on file extension.
     *
     * @param  string  $filePath  Path to the file
     * @param  string  $fileExtension  File extension (pdf, jpg, png, etc.)
     * @param  int  $quality  JPEG quality (1-100)
     * @param  int  $resolution  DPI resolution for PDF rendering
     * @return string Binary JPG image data
     *
     * @throws Exception If conversion fails or unsupported type
     */
    public static function generatePreview(string $filePath, string $fileExtension, int $quality = 85, int $resolution = 144): string
    {
        $extension = strtolower($fileExtension);

        if ($extension === 'pdf') {
            return self::generateFromPdf($filePath, $quality, $resolution);
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
            return self::generateFromImage($filePath, $quality);
        }

        throw new Exception("Unsupported file type for preview generation: {$fileExtension}");
    }

    /**
     * Generate a JPG preview from a PDF file.
     *
     * @param  string  $pdfPath  Path to the PDF file
     * @param  int  $quality  JPEG quality (1-100)
     * @param  int  $resolution  DPI resolution for rendering
     * @return string Binary JPG image data
     *
     * @throws Exception If conversion fails
     */
    public static function generateFromPdf(string $pdfPath, int $quality = 85, int $resolution = 144): string
    {
        if (! file_exists($pdfPath)) {
            throw new Exception("PDF file not found: {$pdfPath}");
        }

        if (! extension_loaded('imagick')) {
            throw new Exception('Imagick extension is not available');
        }

        // Check if Ghostscript is available (required for PDF processing)
        $gsPath = exec('which gs 2>/dev/null');
        if (empty($gsPath)) {
            throw new Exception('Ghostscript (gs) is not available - required for PDF processing');
        }

        try {
            $imagick = new Imagick;
            $imagick->setResolution($resolution, $resolution);
            $imagick->readImage($pdfPath.'[0]'); // Read first page only
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($quality);

            // Flatten image to white background for transparency
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

            $imageData = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            Log::info('[ImagePreviewGenerator] Preview generated successfully', [
                'pdf_path' => $pdfPath,
                'preview_size' => strlen($imageData),
                'quality' => $quality,
                'resolution' => $resolution,
            ]);

            return $imageData;
        } catch (ImagickException $e) {
            Log::error('[ImagePreviewGenerator] Imagick error during preview generation', [
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Failed to generate preview: {$e->getMessage()}");
        }
    }

    /**
     * Generate a JPG preview from an image file.
     * Resizes to maximum 2000px while maintaining aspect ratio.
     *
     * @param  string  $imagePath  Path to the image file
     * @param  int  $quality  JPEG quality (1-100)
     * @return string Binary JPG image data
     *
     * @throws Exception If conversion fails
     */
    protected static function generateFromImage(string $imagePath, int $quality = 85): string
    {
        if (! file_exists($imagePath)) {
            throw new Exception("Image file not found: {$imagePath}");
        }

        if (! extension_loaded('imagick')) {
            throw new Exception('Imagick extension is not available');
        }

        try {
            $imagick = new Imagick($imagePath);

            // Get original dimensions
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            // Resize if larger than 2000px on either dimension
            if ($width > 2000 || $height > 2000) {
                $imagick->thumbnailImage(2000, 2000, true, true);
            }

            // Convert to JPEG
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($quality);

            // Flatten transparency to white background
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

            $imageData = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            Log::info('[ImagePreviewGenerator] Image preview generated successfully', [
                'image_path' => $imagePath,
                'original_size' => "{$width}x{$height}",
                'preview_size' => strlen($imageData),
                'quality' => $quality,
            ]);

            return $imageData;
        } catch (ImagickException $e) {
            Log::error('[ImagePreviewGenerator] Imagick error during image preview generation', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Failed to generate image preview: {$e->getMessage()}");
        }
    }
}
