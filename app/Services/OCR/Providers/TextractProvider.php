<?php

namespace App\Services\OCR\Providers;

use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRService;
use App\Services\OCR\Textract\TextractFileValidator;
use App\Services\OCR\Textract\TextractPdfImageProcessor;
use App\Services\OCR\Textract\TextractResponseParser;
use App\Services\OCR\TextractStorageBridge;
use App\Services\StorageService;
use Aws\Exception\AwsException;
use Aws\Textract\TextractClient;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;
use Throwable;

class TextractProvider implements OCRService
{
    protected TextractClient $client;

    protected string $bucket;

    protected StorageService $storageService;

    protected TextractStorageBridge $storageBridge;

    // AWS Textract sync API limits
    private const SYNC_API_MAX_SIZE = 10 * 1024 * 1024; // 10MB

    // AWS Textract bytes API limit (5MB for direct bytes)
    private const DIRECT_BYTES_MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        StorageService $storageService,
        TextractStorageBridge $storageBridge
    ) {
        $this->storageService = $storageService;
        $this->storageBridge = $storageBridge;
        $this->initializeClient();
    }

    protected function initializeClient(): void
    {
        $this->client = new TextractClient([
            'version' => 'latest',
            'region' => config('ai.ocr.providers.textract.region', 'eu-central-1'),
            'credentials' => [
                'key' => config('ai.ocr.providers.textract.key'),
                'secret' => config('ai.ocr.providers.textract.secret'),
            ],
        ]);

        $this->bucket = config('filesystems.disks.textract.bucket');
    }

    public function extractText(string $filePath, string $fileType, string $fileGuid, array $options = []): OCRResult
    {
        $startTime = microtime(true);

        try {
            // Validate file before processing
            $validationResult = TextractFileValidator::validate($filePath, $this->getSupportedExtensions());
            if (! $validationResult['valid']) {
                return OCRResult::failure($validationResult['error'], $this->getProviderName());
            }

            // Determine processing strategy based on file characteristics
            $strategy = $this->determineProcessingStrategy($filePath);

            Log::info('[TextractProvider] Processing file', [
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
                'strategy' => $strategy,
                'file_size' => filesize($filePath),
            ]);

            // Execute the appropriate strategy
            $result = match ($strategy) {
                'direct_bytes' => $this->processWithDirectBytes($filePath, $fileType, $options),
                's3_async' => $this->processWithS3Async($filePath, $fileType, $fileGuid, $options),
                's3_sync' => $this->processWithS3Sync($filePath, $fileType, $fileGuid, $options),
                default => throw new Exception("Unknown processing strategy: {$strategy}"),
            };

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            return OCRResult::success(
                text: $result['text'],
                provider: $this->getProviderName(),
                metadata: $result['metadata'],
                confidence: $result['confidence'],
                pages: $result['pages'] ?? [],
                blocks: $result['blocks'] ?? [],
                processingTime: $processingTime,
                structuredData: [
                    'forms' => $result['forms'] ?? [],
                    'tables' => $result['tables'] ?? [],
                ]
            );

        } catch (Exception $e) {
            $errorMessage = $this->extractErrorMessage($e);

            Log::error('[TextractProvider] Text extraction failed', [
                'error' => $errorMessage,
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
            ]);

            return OCRResult::failure($errorMessage, $this->getProviderName());
        }
    }

    /**
     * Determine the optimal processing strategy based on file characteristics.
     *
     * Strategy selection:
     * - direct_bytes: Single-page files (PDF or image) under 5MB (no S3 cost)
     * - s3_async: Multi-page PDFs (required for unlimited pages)
     * - s3_sync: Large files over 5MB (requires S3)
     */
    protected function determineProcessingStrategy(string $filePath): string
    {
        $fileSize = filesize($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isPdf = $extension === 'pdf';

        // For PDFs, check page count to determine strategy
        if ($isPdf) {
            $pageCount = $this->getPdfPageCount($filePath);

            // Single-page PDFs under 5MB can use direct bytes
            if ($pageCount === 1 && $fileSize <= self::DIRECT_BYTES_MAX_SIZE) {
                return 'direct_bytes';
            }

            // Multi-page PDFs must use async API via S3
            if ($pageCount > 1) {
                return 's3_async';
            }

            // Single-page PDFs over 5MB need S3 sync
            return 's3_sync';
        }

        // Single-page images under 5MB can use direct bytes (cheaper - no S3 PUT/DELETE)
        if ($fileSize <= self::DIRECT_BYTES_MAX_SIZE) {
            return 'direct_bytes';
        }

        // Larger images use S3 sync API
        return 's3_sync';
    }

    /**
     * Get the page count of a PDF file.
     * Returns 1 if unable to determine (safe fallback).
     */
    protected function getPdfPageCount(string $filePath): int
    {
        try {
            // Check if required dependencies are available
            if (! extension_loaded('imagick')) {
                Log::debug('[TextractProvider] Imagick not available for PDF page counting, assuming single page');
                return 1;
            }

            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::debug('[TextractProvider] Ghostscript not available for PDF page counting, assuming single page');
                return 1;
            }

            // Use Spatie PDF library to count pages
            $pdf = new Pdf($filePath);
            $pageCount = $pdf->pageCount();

            Log::debug('[TextractProvider] PDF page count determined', [
                'file_path' => basename($filePath),
                'page_count' => $pageCount,
            ]);

            return $pageCount;

        } catch (Exception $e) {
            Log::warning('[TextractProvider] Failed to determine PDF page count, assuming single page', [
                'file_path' => basename($filePath),
                'error' => $e->getMessage(),
            ]);
            return 1; // Safe fallback
        }
    }

    /**
     * Process file by sending bytes directly to Textract (no S3 upload).
     * Most cost-effective for single-page files (images or PDFs) under 5MB.
     */
    protected function processWithDirectBytes(string $filePath, string $fileType, array $options = []): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        Log::info('[TextractProvider] Using direct bytes strategy (no S3)', [
            'file_extension' => $extension,
            'file_size' => filesize($filePath),
            'file_type' => $fileType,
        ]);

        $fileBytes = file_get_contents($filePath);
        if ($fileBytes === false) {
            throw new Exception('Could not read file contents');
        }

        try {
            // Use appropriate API based on file type
            if ($fileType === 'receipt') {
                $result = $this->client->analyzeExpense([
                    'Document' => [
                        'Bytes' => $fileBytes,
                    ],
                ]);
            } else {
                $result = $this->client->detectDocumentText([
                    'Document' => [
                        'Bytes' => $fileBytes,
                    ],
                ]);
            }

            $parsed = $this->parseResultByFileType($result->toArray(), $fileType);

            return $this->cleanupBlocks($parsed);

        } catch (AwsException $e) {
            $this->logAwsError($e, 'direct bytes');
            throw new Exception($this->extractErrorMessage($e));
        }
    }

    /**
     * Process file via S3 using async API (required for multi-page PDFs).
     */
    protected function processWithS3Async(string $filePath, string $fileType, string $fileGuid, array $options = []): array
    {
        $pageCount = $this->getPdfPageCount($filePath);
        Log::info('[TextractProvider] Using S3 async strategy (multi-page PDF)', [
            'file_size' => filesize($filePath),
            'page_count' => $pageCount,
            'file_type' => $fileType,
        ]);

        $s3Path = $this->uploadToS3($filePath, $fileGuid);

        try {
            $result = $this->extractViaAsyncApi($s3Path, $fileType);

            return $result;
        } finally {
            $this->cleanupS3File($s3Path);
        }
    }

    /**
     * Process file via S3 using sync API (large single-page files over 5MB).
     */
    protected function processWithS3Sync(string $filePath, string $fileType, string $fileGuid, array $options = []): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        Log::info('[TextractProvider] Using S3 sync strategy (large file)', [
            'file_extension' => $extension,
            'file_size' => filesize($filePath),
            'file_type' => $fileType,
        ]);

        $s3Path = $this->uploadToS3($filePath, $fileGuid);

        try {
            // Use appropriate API based on file type
            if ($fileType === 'receipt') {
                $result = $this->client->analyzeExpense([
                    'Document' => [
                        'S3Object' => [
                            'Bucket' => $this->bucket,
                            'Name' => $s3Path,
                        ],
                    ],
                ]);
            } else {
                $result = $this->client->detectDocumentText([
                    'Document' => [
                        'S3Object' => [
                            'Bucket' => $this->bucket,
                            'Name' => $s3Path,
                        ],
                    ],
                ]);
            }

            $parsed = $this->parseResultByFileType($result->toArray(), $fileType);

            return $this->cleanupBlocks($parsed);

        } catch (AwsException $e) {
            $this->logAwsError($e, $s3Path);

            // Fallback for unsupported PDFs
            if ($e->getAwsErrorCode() === 'UnsupportedDocumentException' && str_ends_with($s3Path, '.pdf')) {
                Log::info('[TextractProvider] Attempting PDF to images fallback');
                return TextractPdfImageProcessor::process($this->client, $this->bucket, $s3Path, $options);
            }

            throw new Exception($this->extractErrorMessage($e));
        } finally {
            $this->cleanupS3File($s3Path);
        }
    }

    /**
     * Extract text using async Textract API (polls for completion).
     */
    protected function extractViaAsyncApi(string $s3Path, string $fileType): array
    {
        // Start async job with appropriate API
        if ($fileType === 'receipt') {
            $result = $this->client->startExpenseAnalysis([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $this->bucket,
                        'Name' => $s3Path,
                    ],
                ],
            ]);
        } else {
            $result = $this->client->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $this->bucket,
                        'Name' => $s3Path,
                    ],
                ],
            ]);
        }

        $jobId = $result->get('JobId');

        Log::info('[TextractProvider] Async job started', [
            'job_id' => $jobId,
            's3_path' => $s3Path,
            'file_type' => $fileType,
            'api_used' => $fileType === 'receipt' ? 'StartExpenseAnalysis' : 'StartDocumentTextDetection',
        ]);

        // Poll for completion
        $response = $this->pollForCompletion($jobId, $fileType);

        // Handle expense analysis differently (doesn't use block streaming)
        if ($fileType === 'receipt') {
            $parsed = TextractResponseParser::parseExpense($response->toArray());
            return $this->cleanupBlocks($parsed);
        }

        // Stream paginated blocks to disk to avoid holding large responses in memory (documents only)
        $streamed = $this->streamAsyncBlocksToDisk($jobId, $response, $fileType);

        try {
            $parsed = $this->parseStreamedAsyncBlocks($streamed, $response->get('DocumentMetadata') ?? [], $fileType);

            return $this->cleanupBlocks($parsed);
        } finally {
            $this->cleanupStreamedAsyncBlocks($streamed);
        }
    }

    /**
     * Poll Textract async job until completion.
     * Uses appropriate polling method based on file type (expense vs document).
     */
    protected function pollForCompletion(string $jobId, string $fileType): \Aws\Result
    {
        $maxAttempts = config('ai.ocr.providers.textract.max_polling_attempts', 60);
        $pollingInterval = config('ai.ocr.providers.textract.polling_interval', 10);
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep($pollingInterval);
            $attempt++;

            // Use appropriate polling method based on file type
            if ($fileType === 'receipt') {
                $response = $this->client->getExpenseAnalysis(['JobId' => $jobId]);
            } else {
                $response = $this->client->getDocumentTextDetection(['JobId' => $jobId]);
            }

            $status = $response->get('JobStatus');

            Log::debug('[TextractProvider] Polling async job', [
                'job_id' => $jobId,
                'status' => $status,
                'attempt' => $attempt,
                'file_type' => $fileType,
            ]);

            if ($status === 'SUCCEEDED' || $status === 'PARTIAL_SUCCESS') {
                Log::info('[TextractProvider] Async job completed', [
                    'job_id' => $jobId,
                    'status' => $status,
                    'attempts' => $attempt,
                    'total_time' => $attempt * $pollingInterval,
                ]);
                return $response;
            }

            if ($status === 'FAILED') {
                $message = $response->get('StatusMessage') ?? 'Unknown error';
                throw new Exception("Textract async job failed: {$message}");
            }
        }

        $totalSeconds = $maxAttempts * $pollingInterval;
        throw new Exception("Textract async job timed out after {$totalSeconds} seconds. Job ID: {$jobId}");
    }

    /**
     * Stream all pages from async job to per-page JSONL files on disk.
     *
     * This avoids accumulating large block arrays in memory (which can exhaust the worker memory limit).
     */
    protected function streamAsyncBlocksToDisk(string $jobId, \Aws\Result $response, string $fileType): array
    {
        $baseDir = storage_path("app/textract/async/{$jobId}");
        if (! is_dir($baseDir) && ! mkdir($baseDir, 0755, true) && ! is_dir($baseDir)) {
            throw new Exception("Unable to create temp directory for Textract results: {$baseDir}");
        }

        $handlesByPage = [];
        $pagesSeen = [];
        $totalBlocks = 0;
        $nextToken = null;
        $page = 0;

        $writeBlocks = function (array $blocks) use ($baseDir, &$handlesByPage, &$pagesSeen, &$totalBlocks): void {
            foreach ($blocks as $block) {
                $pageNum = (int) ($block['Page'] ?? 1);
                $pagesSeen[$pageNum] = true;
                $totalBlocks++;

                if (! isset($handlesByPage[$pageNum])) {
                    $path = "{$baseDir}/page-{$pageNum}.jsonl";
                    $handle = fopen($path, 'ab');
                    if ($handle === false) {
                        throw new Exception("Unable to open temp Textract page file: {$path}");
                    }
                    $handlesByPage[$pageNum] = $handle;
                }

                $json = json_encode($block);
                if ($json === false) {
                    continue;
                }
                fwrite($handlesByPage[$pageNum], $json."\n");
            }
        };

        try {
            $blocks = $response->get('Blocks') ?? [];
            $writeBlocks($blocks);
            $nextToken = $response->get('NextToken');

            while ($nextToken) {
                $page++;

                // Use appropriate polling method based on file type
                if ($fileType === 'receipt') {
                    $nextResponse = $this->client->getExpenseAnalysis([
                        'JobId' => $jobId,
                        'NextToken' => $nextToken,
                    ]);
                } else {
                    $nextResponse = $this->client->getDocumentTextDetection([
                        'JobId' => $jobId,
                        'NextToken' => $nextToken,
                    ]);
                }

                $blocks = $nextResponse->get('Blocks') ?? [];
                $writeBlocks($blocks);
                $nextToken = $nextResponse->get('NextToken');

                Log::debug('[TextractProvider] Fetched additional page', [
                    'job_id' => $jobId,
                    'total_blocks' => $totalBlocks,
                    'page' => $page,
                ]);
            }
        } finally {
            foreach ($handlesByPage as $handle) {
                fclose($handle);
            }
        }

        $pages = array_keys($pagesSeen);
        sort($pages);

        return [
            'job_id' => $jobId,
            'base_dir' => $baseDir,
            'pages' => $pages,
            'total_blocks' => $totalBlocks,
        ];
    }

    /**
     * Parse streamed async blocks from disk into the standard result shape.
     */
    protected function parseStreamedAsyncBlocks(array $streamed, array $documentMetadata, string $fileType): array
    {
        $pages = $streamed['pages'] ?? [];
        $baseDir = $streamed['base_dir'] ?? null;
        $totalBlocks = (int) ($streamed['total_blocks'] ?? 0);

        if (! is_string($baseDir) || $baseDir === '') {
            throw new Exception('Missing streamed Textract base_dir');
        }

        $maxBlocksInMemory = (int) config('ai.ocr.options.max_blocks_in_memory', 5000);
        $storeBlocks = (bool) config('ai.ocr.options.store_blocks', false);
        $canReturnBlocks = $storeBlocks && $totalBlocks > 0 && $totalBlocks <= $maxBlocksInMemory;

        if ($storeBlocks && ! $canReturnBlocks) {
            Log::warning('[TextractProvider] Skipping returning blocks to avoid memory exhaustion', [
                'job_id' => $streamed['job_id'] ?? null,
                'total_blocks' => $totalBlocks,
                'max_blocks_in_memory' => $maxBlocksInMemory,
            ]);
        }

        if ($fileType === 'document') {
            $text = '';
            $confidenceSum = 0.0;
            $lineCount = 0;
            $blockCount = 0;
            $returnedBlocks = $canReturnBlocks ? [] : [];

            foreach ($pages as $pageNum) {
                $pagePath = "{$baseDir}/page-{$pageNum}.jsonl";
                $pageBlocks = $this->readJsonlBlocks($pagePath);
                $blockCount += count($pageBlocks);

                if ($canReturnBlocks) {
                    $returnedBlocks = array_merge($returnedBlocks, $pageBlocks);
                }

                $pageParsed = TextractResponseParser::parseDocumentPage($pageBlocks, (int) $pageNum);
                $text .= $pageParsed['text'];
                $confidenceSum += $pageParsed['confidence_sum'];
                $lineCount += $pageParsed['line_count'];
            }

            return [
                'text' => trim($text),
                'metadata' => [
                    'page_count' => count($pages),
                    'block_count' => $blockCount,
                    'line_count' => $lineCount,
                    'extraction_type' => 'document_analysis',
                    'textract_job_id' => $streamed['job_id'] ?? null,
                    'blocks_returned' => $canReturnBlocks,
                    'blocks_total' => $totalBlocks,
                ],
                'confidence' => $lineCount > 0 ? $confidenceSum / $lineCount / 100 : 0,
                'pages' => $pages,
                'blocks' => $returnedBlocks,
                'forms' => [],
                'tables' => [],
            ];
        }

        // Structured parsing, processed per-page to avoid high memory use.
        $text = '';
        $confidenceSum = 0.0;
        $lineCount = 0;
        $blockCount = 0;
        $forms = [];
        $tables = [];
        $returnedBlocks = $canReturnBlocks ? [] : [];

        foreach ($pages as $pageNum) {
            $pagePath = "{$baseDir}/page-{$pageNum}.jsonl";
            $pageBlocks = $this->readJsonlBlocks($pagePath);
            $blockCount += count($pageBlocks);

            if ($canReturnBlocks) {
                $returnedBlocks = array_merge($returnedBlocks, $pageBlocks);
            }

            $pageParsed = TextractResponseParser::parseStructuredPage($pageBlocks);
            $text .= $pageParsed['text'];
            $confidenceSum += $pageParsed['confidence_sum'];
            $lineCount += $pageParsed['line_count'];
            $forms = array_merge($forms, $pageParsed['forms']);
            $tables = array_merge($tables, $pageParsed['tables']);
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'page_count' => count($pages),
                'block_count' => $blockCount,
                'line_count' => $lineCount,
                'extraction_type' => $fileType,
                'textract_job_id' => $streamed['job_id'] ?? null,
                'blocks_returned' => $canReturnBlocks,
                'blocks_total' => $totalBlocks,
            ],
            'confidence' => $lineCount > 0 ? $confidenceSum / $lineCount / 100 : 0,
            'pages' => $pages,
            'blocks' => $returnedBlocks,
            'forms' => $forms,
            'tables' => $tables,
        ];
    }

    /**
     * Read blocks from a JSONL file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readJsonlBlocks(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new Exception("Unable to read streamed Textract blocks: {$path}");
        }

        try {
            $blocks = [];
            while (! feof($handle)) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $blocks[] = $decoded;
                }
            }

            return $blocks;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Cleanup streamed async block files.
     */
    protected function cleanupStreamedAsyncBlocks(array $streamed): void
    {
        $baseDir = $streamed['base_dir'] ?? null;
        if (! is_string($baseDir) || $baseDir === '' || ! is_dir($baseDir)) {
            return;
        }

        try {
            $files = glob($baseDir.'/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($baseDir);
        } catch (Throwable $e) {
            Log::warning('[TextractProvider] Failed to cleanup streamed Textract files', [
                'base_dir' => $baseDir,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Upload file to S3 Textract bucket.
     */
    protected function uploadToS3(string $filePath, string $fileGuid): string
    {
        $s3Path = "temp/{$fileGuid}/".basename($filePath);
        $textractDisk = Storage::disk('textract');

        Log::debug('[TextractProvider] Uploading to S3', [
            's3_path' => $s3Path,
            'bucket' => $this->bucket,
        ]);

        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            throw new Exception('Could not open file for S3 upload');
        }

        try {
            if (method_exists($textractDisk, 'writeStream')) {
                $textractDisk->writeStream($s3Path, $stream);
            } else {
                $textractDisk->put($s3Path, $stream);
            }
        } finally {
            fclose($stream);
        }

        return $s3Path;
    }

    /**
     * Clean up temporary S3 file.
     */
    protected function cleanupS3File(string $s3Path): void
    {
        try {
            Storage::disk('textract')->delete($s3Path);
            Log::debug('[TextractProvider] Cleaned up S3 file', ['s3_path' => $s3Path]);
        } catch (Exception $e) {
            Log::warning('[TextractProvider] Failed to cleanup S3 file', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get feature types based on file type.
     *
     * Note: Returns empty array as we use specialized APIs instead:
     * - Receipts: AnalyzeExpense API
     * - Documents: DetectDocumentText API
     */
    protected function getFeatureTypesForFileType(string $fileType): array
    {
        return match ($fileType) {
            'receipt' => [],
            'document' => [],
            default => [],
        };
    }

    /**
     * Parse Textract result based on file type.
     */
    protected function parseResultByFileType(array $result, string $fileType): array
    {
        return match ($fileType) {
            'receipt' => TextractResponseParser::parseExpense($result),
            'document' => TextractResponseParser::parseBasic($result, 'document'),
            default => TextractResponseParser::parseBasic($result, 'document'),
        };
    }

    /**
     * Remove blocks from result if configured to do so.
     */
    protected function cleanupBlocks(array $parsed): array
    {
        if (! (bool) config('ai.ocr.options.store_blocks', false)) {
            unset($parsed['blocks']);
        }
        return $parsed;
    }

    /**
     * Extract error message from exception.
     */
    protected function extractErrorMessage(Exception $e): string
    {
        $errorMessage = $e->getMessage();

        if ($e instanceof AwsException) {
            $errorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
        }

        if (is_array($errorMessage)) {
            $errorMessage = json_encode($errorMessage);
        }

        return $errorMessage;
    }

    /**
     * Log AWS error details.
     */
    protected function logAwsError(AwsException $e, string $context): void
    {
        Log::error('[TextractProvider] AWS Textract error', [
            'context' => $context,
            'aws_error_code' => $e->getAwsErrorCode(),
            'aws_error_type' => $e->getAwsErrorType(),
            'aws_error_message' => $e->getAwsErrorMessage(),
            'status_code' => $e->getStatusCode(),
        ]);
    }

    public function canHandle(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->getSupportedExtensions());
    }

    public function getProviderName(): string
    {
        return 'textract';
    }

    public function getSupportedExtensions(): array
    {
        return ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif'];
    }

    public function getCapabilities(): array
    {
        return [
            'text_extraction' => true,
            'layout_analysis' => true,
            'table_extraction' => true,
            'form_extraction' => true,
            'multi_page' => true,
            'languages' => ['en', 'es', 'it', 'pt', 'fr', 'de'],
            'max_file_size' => '10MB',
            'supported_formats' => $this->getSupportedExtensions(),
        ];
    }
}
