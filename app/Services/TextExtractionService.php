<?php

namespace App\Services;

use App\Services\OCR\ExtractionCache;
use App\Services\OCR\OcrErrorFormatter;
use App\Services\OCR\OCRServiceFactory;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Coordinates OCR extraction for files and caches results.
 *
 * - Selects provider via configuration
 * - Applies fallback strategies and confidence checks
 * - Persists/reads cached extraction results
 */
class TextExtractionService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Extract both text and structured data from a file using OCR providers.
     *
     * @param  string  $filePath  Absolute path to the working file
     * @param  string  $fileType  Either 'receipt' or 'document'
     * @param  string  $fileGuid  Unique file GUID for caching
     * @return array{text:string,structured_data:array,blocks:array,ocr_metadata:array,provider:string}
     *
     * @throws Exception
     */
    public function extractWithStructuredData(string $filePath, string $fileType, string $fileGuid): array
    {
        try {
            // Check cache first
            if (config('ai.ocr.options.cache_results', true)) {
                $cached = ExtractionCache::get($fileGuid);
                if ($cached !== null) {
                    Log::debug('[TextExtractionService] Using cached text', ['file_guid' => $fileGuid]);

                    return [
                        'text' => $cached['text'],
                        'structured_data' => $cached['structured'],
                        'blocks' => [],
                        'ocr_metadata' => [],
                        'provider' => config('ai.ocr.provider', 'textract'),
                    ];
                }
            }

            // Simplified: single provider flow
            $primaryProvider = config('ai.ocr.provider', 'textract');
            $allProviders = [$primaryProvider];

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
            if (! $result || ! $result->success) {
                $errorMessage = OcrErrorFormatter::format($lastError ?? 'All OCR providers failed', $primaryProvider);
                throw new Exception($errorMessage);
            }

            $text = $result->text;
            $structuredData = $result->structuredData;

            // Apply confidence filtering if needed
            $minConfidence = config('ai.ocr.options.min_confidence', 0.8);
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

            // Cache both text and structured data
            if (config('ai.ocr.options.cache_results', true)) {
                ExtractionCache::put($fileGuid, $text, $structuredData);
            }

            Log::info('[TextExtractionService] Text and structured data extracted successfully', [
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
                'provider' => $result->provider,
                'confidence' => $result->confidence,
                'text_length' => strlen($text),
                'forms_count' => count($structuredData['forms'] ?? []),
                'tables_count' => count($structuredData['tables'] ?? []),
                'processing_time' => $result->processingTime,
            ]);

            return [
                'text' => $text,
                'structured_data' => $structuredData,
                'blocks' => $result->blocks,
                'ocr_metadata' => $result->metadata,
                'provider' => $result->provider,
            ];

        } catch (Exception $e) {
            Log::error('[TextExtractionService] Text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'file_type' => $fileType,
            ]);
            throw $e;
        }
    }

    /**
     * Extract text from a file using OCR providers.
     */
    public function extract(string $filePath, string $fileType, string $fileGuid): string
    {
        $result = $this->extractWithStructuredData($filePath, $fileType, $fileGuid);

        return $result['text'];
    }

    /**
     * Fallback PDF text extraction using a local parser, if available.
     */
    protected function extractPdfTextFallback(string $filePath): string
    {
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser;
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();

                Log::info('[TextExtractionService] Used PDF parser fallback', [
                    'file_path' => $filePath,
                    'text_length' => strlen($text),
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
     * Clear cached OCR results for a file.
     */
    public function clearCache(string $fileGuid): void
    {
        ExtractionCache::clear($fileGuid);
        Log::debug('[TextExtractionService] Cache cleared', [
            'file_guid' => $fileGuid,
        ]);
    }

    /**
     * Format error messages with user-friendly information
     */
    // Error formatting moved to OcrErrorFormatter

    /**
     * Get available OCR providers and their capabilities.
     *
     * @return array<string,array{
     *   name:string,
     *   capabilities:array,
     *   supported_extensions:array,
     *   available:bool,
     *   error?:string
     * }>
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
