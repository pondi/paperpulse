<?php

namespace App\Services;

use App\Services\OCR\OCRServiceFactory;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TextExtractionService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Extract text from a file using OCR providers
     */
    public function extract(string $filePath, string $fileType, string $fileGuid): string
    {
        try {
            // Check cache first
            $cacheKey = "text_extraction.{$fileGuid}";
            $cachedText = Cache::get($cacheKey);

            if ($cachedText !== null && config('ocr.options.cache_results', true)) {
                Log::debug('[TextExtractionService] Using cached text', [
                    'file_guid' => $fileGuid,
                ]);

                return $cachedText;
            }

            // Get the appropriate OCR provider for this file
            $ocrProvider = OCRServiceFactory::createForFile($filePath);

            Log::info('[TextExtractionService] Starting OCR extraction', [
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
                'provider' => $ocrProvider->getProviderName(),
            ]);

            // Extract text using the OCR provider
            $result = $ocrProvider->extractText($filePath, $fileType, $fileGuid);

            if (! $result->success) {
                throw new Exception("OCR extraction failed: {$result->error}");
            }

            $text = $result->text;

            // Apply confidence filtering if needed
            $minConfidence = config('ocr.options.min_confidence', 0.8);
            if ($result->confidence < $minConfidence) {
                Log::warning('[TextExtractionService] Low confidence OCR result', [
                    'file_guid' => $fileGuid,
                    'confidence' => $result->confidence,
                    'min_confidence' => $minConfidence,
                ]);

                // Try fallback with PDF parser if available
                if (pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
                    $fallbackText = $this->extractPdfTextFallback($filePath);
                    if (! empty($fallbackText)) {
                        $text = $fallbackText;
                    }
                }
            }

            // Cache the extracted text
            if (config('ocr.options.cache_results', true)) {
                $cacheDuration = now()->addDays(config('ocr.options.cache_duration', 7));
                Cache::put($cacheKey, $text, $cacheDuration);
            }

            Log::info('[TextExtractionService] Text extracted successfully', [
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
                'provider' => $result->provider,
                'confidence' => $result->confidence,
                'text_length' => strlen($text),
                'processing_time' => $result->processingTime,
            ]);

            return $text;

        } catch (Exception $e) {
            Log::error('[TextExtractionService] Text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
            ]);
            throw $e;
        }
    }

    /**
     * Fallback PDF text extraction using PHP
     */
    protected function extractPdfTextFallback(string $filePath): string
    {
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser;
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();

                Log::debug('[TextExtractionService] Used PDF parser fallback', [
                    'file_path' => $filePath,
                ]);

                return $text;
            }

            Log::warning('[TextExtractionService] No PDF parser available for fallback');

            return '';
        } catch (Exception $e) {
            Log::error('[TextExtractionService] PDF fallback extraction failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return '';
        }
    }

    /**
     * Clear cached text for a file
     */
    public function clearCache(string $fileGuid): void
    {
        $cacheKey = "text_extraction.{$fileGuid}";
        Cache::forget($cacheKey);

        Log::debug('[TextExtractionService] Cache cleared', [
            'file_guid' => $fileGuid,
        ]);
    }

    /**
     * Get available OCR providers and their capabilities
     */
    public function getAvailableProviders(): array
    {
        $providers = [];

        foreach (OCRServiceFactory::getAvailableProviders() as $providerName) {
            try {
                $provider = OCRServiceFactory::create($providerName);
                $providers[$providerName] = [
                    'name' => $provider->getProviderName(),
                    'capabilities' => $provider->getCapabilities(),
                    'supported_extensions' => $provider->getSupportedExtensions(),
                    'available' => OCRServiceFactory::isProviderAvailable($providerName),
                ];
            } catch (Exception $e) {
                $providers[$providerName] = [
                    'name' => $providerName,
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $providers;
    }
}
