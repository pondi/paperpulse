<?php

namespace App\Services\OCR\Providers;

use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRService;
use Exception;
use Illuminate\Support\Facades\Log;

class TesseractProvider implements OCRService
{
    protected string $tesseractEndpoint;

    protected array $defaultOptions;

    public function __construct()
    {
        $this->tesseractEndpoint = config('ocr.tesseract.endpoint', 'http://tesseract-service:8080');
        $this->defaultOptions = [
            'language' => config('ocr.tesseract.language', 'nor+eng'),
            'psm' => config('ocr.tesseract.psm', 3), // Page Segmentation Mode
            'oem' => config('ocr.tesseract.oem', 3), // OCR Engine Mode
        ];
    }

    public function extractText(string $filePath, string $fileType, string $fileGuid, array $options = []): OCRResult
    {
        $startTime = microtime(true);

        try {
            // This would be implemented when Tesseract service is available
            // For now, return a placeholder implementation

            if (! $this->isServiceAvailable()) {
                return OCRResult::failure('Tesseract service is not available', $this->getProviderName());
            }

            $extractedData = $this->callTesseractService($filePath, $fileType, array_merge($this->defaultOptions, $options));
            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            return OCRResult::success(
                text: $extractedData['text'],
                provider: $this->getProviderName(),
                metadata: $extractedData['metadata'],
                confidence: $extractedData['confidence'],
                processingTime: $processingTime
            );

        } catch (Exception $e) {
            Log::error('[TesseractProvider] Text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
            ]);

            return OCRResult::failure($e->getMessage(), $this->getProviderName());
        }
    }

    protected function isServiceAvailable(): bool
    {
        // Check if Tesseract service is running
        // This is a placeholder - implement actual health check
        return false; // Set to false until service is implemented
    }

    protected function callTesseractService(string $filePath, string $fileType, array $options): array
    {
        // Placeholder for actual Tesseract API call
        // This would make HTTP requests to the Tesseract service

        throw new Exception('Tesseract service integration not yet implemented');
    }

    public function canHandle(string $filePath): bool
    {
        if (! $this->isServiceAvailable()) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->getSupportedExtensions());
    }

    public function getProviderName(): string
    {
        return 'tesseract';
    }

    public function getSupportedExtensions(): array
    {
        return ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif', 'bmp', 'gif', 'webp'];
    }

    public function getCapabilities(): array
    {
        return [
            'text_extraction' => true,
            'layout_analysis' => false,
            'table_extraction' => false,
            'form_extraction' => false,
            'multi_page' => true,
            'languages' => ['eng', 'nor', 'swe', 'dan', 'deu', 'fra', 'spa', 'ita'],
            'max_file_size' => '50MB',
            'supported_formats' => $this->getSupportedExtensions(),
        ];
    }
}
