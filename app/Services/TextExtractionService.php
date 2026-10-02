<?php

namespace App\Services;

use App\Models\File;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\OCR\ExtractionCache;
use App\Services\OCR\OcrErrorFormatter;
use App\Services\OCR\OCRServiceFactory;
use App\Services\Workers\WorkerFileManager;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

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

    public function extractFromFile(int $fileId, int $userId, string $fileType): string
    {
        if (! in_array($fileType, ['receipt', 'document'], true)) {
            throw new Exception('Unsupported batch file type.');
        }
        $file = File::withoutGlobalScope('user')->where('user_id', $userId)
            ->where('file_type', $fileType)->findOrFail($fileId);
        $path = $file->s3_archive_path ?? $file->s3_original_path;
        $extension = $file->s3_archive_path ? 'pdf' : strtolower($file->fileExtension);
        if (! $path || ! in_array($extension, OCRServiceFactory::create()->getSupportedExtensions(), true)) {
            throw new Exception('The stored file has no supported extraction source. Office files must finish conversion first.');
        }

        return app(WorkerFileManager::class)->processWithCleanup(
            $path,
            $file->guid.'-batch-'.Str::uuid(),
            $extension,
            fn (string $localPath): string => $this->extract($localPath, $fileType, $file->guid),
            'BatchExtraction',
        );
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
        $file = File::withoutGlobalScope('user')->where('guid', $fileGuid)->first();
        $operation = function () use ($filePath, $fileType, $fileGuid): array {
            $result = $this->extractUncachedStructuredData($filePath, $fileType, $fileGuid);
            if (trim($result['text']) === '' && empty($result['structured_data'])) {
                throw new Exception('OCR returned no usable content');
            }

            return $result;
        };
        if ($file && config('ai.ocr.options.cache_results', true)) {
            $result = ProcessingStageCache::remember($file->user_id, hash_file('sha256', $filePath), 'ocr', [
                'provider' => config('ai.ocr.provider', 'textract'), 'schema_version' => 1,
                'options' => config('ai.ocr.options'), 'file_type' => $fileType,
            ], $operation, $fileGuid);
        } else {
            $result = $operation();
        }
        if ($file && ($result['ocr_metadata']['needs_review'] ?? false)) {
            $file->status = 'needs_review';
            $file->meta = array_merge($file->meta ?? [], ['processing_coverage' => $result['ocr_metadata'], 'review' => ['reason' => 'processing_limit']]);
            $file->save();
        }

        return $result;
    }

    protected function extractUncachedStructuredData(string $filePath, string $fileType, string $fileGuid): array
    {
        try {
            // Simplified: single provider flow
            $primaryProvider = config('ai.ocr.provider', 'textract');
            $allProviders = [$primaryProvider];

            $lastError = null;
            $result = null;

            // Try each provider in order
            foreach ($allProviders as $providerName) {
                $ocrProvider = OCRServiceFactory::create($providerName);
                try {

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
                } catch (Exception $e) {
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
            if (class_exists(Parser::class)) {
                $parser = new Parser;
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
