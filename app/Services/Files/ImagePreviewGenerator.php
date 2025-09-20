<?php

namespace App\Services\Files;

use Exception;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Generates image previews from PDF files.
 * Single responsibility: Convert PDF to JPG preview.
 */
class ImagePreviewGenerator
{
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
}
