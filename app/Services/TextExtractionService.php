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

            // Get the list of providers to try (primary + fallbacks)
            $primaryProvider = config('ocr.provider', 'textract');
            $fallbackProviders = explode(',', config('ocr.fallback_providers', ''));
            $fallbackProviders = array_map('trim', $fallbackProviders);
            $allProviders = array_merge([$primaryProvider], $fallbackProviders);
            $allProviders = array_unique(array_filter($allProviders));

            $lastError = null;
            $result = null;

            // Try each provider in order
            foreach ($allProviders as $providerName) {
                try {
                    $ocrProvider = OCRServiceFactory::create($providerName);
                    
                    Log::info('[TextExtractionService] Starting OCR extraction', [
                        'file_guid' => $fileGuid,
                        'file_type' => $fileType,
                        'provider' => $ocrProvider->getProviderName(),
                        'attempt' => $providerName,
                    ]);

                    // Extract text using the current OCR provider
                    $result = $ocrProvider->extractText($filePath, $fileType, $fileGuid);

                    if ($result->success) {
                        Log::info('[TextExtractionService] OCR extraction successful', [
                            'file_guid' => $fileGuid,
                            'provider' => $providerName,
                        ]);
                        break; // Success! Exit the loop
                    } else {
                        $lastError = $result->error;
                        Log::warning('[TextExtractionService] OCR provider failed, trying next', [
                            'file_guid' => $fileGuid,
                            'failed_provider' => $providerName,
                            'error' => $result->error,
                        ]);
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('[TextExtractionService] OCR provider exception, trying next', [
                        'file_guid' => $fileGuid,
                        'failed_provider' => $providerName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If all providers failed, throw an error
            if (!$result || !$result->success) {
                $errorMessage = $this->formatErrorMessage($lastError ?? 'All OCR providers failed', $primaryProvider);
                throw new Exception($errorMessage);
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
     * Format error messages with user-friendly information
     */
    protected function formatErrorMessage(string $error, string $provider): string
    {
        // Handle specific PDF format issues from Textract
        if (str_contains($error, 'PDF format is not supported by Textract')) {
            return 'This PDF format is not supported by AWS Textract. The PDF may be password-protected, encrypted, XFA-based, or contain unsupported image formats (JPEG 2000). Please try converting it to a standard PDF format or use a different file.';
        }

        // Handle common Textract errors
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

        // Handle fallback failure
        if (str_contains($error, 'All OCR providers failed')) {
            return 'Document processing failed with all available OCR providers. This file format may not be supported, or the document may be corrupted. Please try converting the file to a standard PDF, PNG, or JPG format.';
        }

        // For provider-specific errors, add context
        $providerName = ucfirst($provider);

        if (str_contains($error, 'timeout')) {
            return "OCR processing timed out using {$providerName}. The file might be too complex or large. Please try a simpler file.";
        }

        return "{$providerName} OCR processing failed: {$error}";
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
