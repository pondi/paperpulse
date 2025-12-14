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
// PDF handling is delegated to TextractPdfImageProcessor
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TextractProvider implements OCRService
{
    protected TextractClient $client;

    protected string $bucket;

    protected StorageService $storageService;

    protected TextractStorageBridge $storageBridge;

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

            // Read file content
            $fileContent = file_get_contents($filePath);

            if (! $fileContent) {
                return OCRResult::failure('Could not read file content', $this->getProviderName());
            }

            // Upload to Textract bucket temporarily
            $textractPath = "temp/{$fileGuid}/".basename($filePath);
            $textractDisk = Storage::disk('textract');

            // Log file details before uploading to Textract
            Log::info('[TextractProvider] Uploading file to Textract', [
                'file_guid' => $fileGuid,
                'file_path' => $filePath,
                'textract_path' => $textractPath,
                'file_size' => strlen($fileContent),
                'file_type' => $fileType,
                'bucket' => $this->bucket,
            ]);

            $textractDisk->put($textractPath, $fileContent);

            try {
                // Wrap the match expression to catch any array to string conversion errors
                try {
                    $result = match ($fileType) {
                        'receipt' => $this->extractReceiptText($textractPath, $options),
                        'document' => $this->extractDocumentText($textractPath, $options),
                        default => throw new Exception("Unknown file type: {$fileType}"),
                    };
                } catch (Throwable $matchError) {
                    // Handle any error including array to string conversion
                    $errorMsg = $matchError->getMessage();
                    if (is_array($errorMsg)) {
                        $errorMsg = json_encode($errorMsg);
                    }
                    throw new Exception('Error during text extraction: '.$errorMsg);
                }

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

            } finally {
                // Clean up temporary file
                try {
                    $textractDisk->delete($textractPath);
                } catch (Exception $e) {
                    Log::warning('[TextractProvider] Could not delete temporary file', [
                        'path' => $textractPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Exception $e) {
            // Handle AWS exceptions that might have array messages
            $errorMessage = $e->getMessage();
            if ($e instanceof AwsException) {
                $errorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
            }
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }

            Log::error('[TextractProvider] Text extraction failed', [
                'error' => $errorMessage,
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
            ]);

            return OCRResult::failure($errorMessage, $this->getProviderName());
        }
    }

    protected function extractReceiptText(string $s3Path, array $options = []): array
    {
        // Use analyzeDocument for receipts to get forms/tables data
        $featureTypes = ['TABLES', 'FORMS'];

        $result = $this->client->analyzeDocument([
            'Document' => [
                'S3Object' => [
                    'Bucket' => $this->bucket,
                    'Name' => $s3Path,
                ],
            ],
            'FeatureTypes' => $featureTypes,
        ]);

        return TextractResponseParser::parseStructured($result->toArray(), 'receipt');
    }

    protected function extractDocumentText(string $s3Path, array $options = []): array
    {
        $featureTypes = $options['feature_types'] ?? ['LAYOUT', 'TABLES', 'FORMS'];

        // Additional validation before calling Textract
        $textractDisk = Storage::disk('textract');
        if (! $textractDisk->exists($s3Path)) {
            throw new Exception("File not found in Textract bucket: {$s3Path}");
        }

        $fileSize = $textractDisk->size($s3Path);

        if ($fileSize === 0) {
            throw new Exception('File is empty in S3 bucket');
        }

        // Use async API for multi-page documents (PDFs) to support unlimited pages
        // Sync API (analyzeDocument) only supports single page
        $useAsync = str_ends_with(strtolower($s3Path), '.pdf') || ($options['force_async'] ?? false);

        if ($useAsync) {
            Log::info('[TextractProvider] Using async API for multi-page document', [
                's3_path' => $s3Path,
                'bucket' => $this->bucket,
                'file_size' => $fileSize,
                'feature_types' => $featureTypes,
            ]);

            return $this->extractDocumentTextAsync($s3Path, $featureTypes, $options);
        }

        // Sync API for single-page images
        Log::info('[TextractProvider] Using sync API for single-page document', [
            's3_path' => $s3Path,
            'bucket' => $this->bucket,
            'file_size' => $fileSize,
            'feature_types' => $featureTypes,
        ]);

        // Check if file size is within sync API limits
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit for sync API
            throw new Exception("File size ({$fileSize} bytes) exceeds Textract sync API limit of 10MB");
        }

        try {
            $result = $this->client->analyzeDocument([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $this->bucket,
                        'Name' => $s3Path,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
            ]);

            return TextractResponseParser::parseDocument($result->toArray());
        } catch (AwsException $e) {
            Log::error('[TextractProvider] AWS Textract error details', [
                's3_path' => $s3Path,
                'bucket' => $this->bucket,
                'file_size' => $fileSize,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_type' => $e->getAwsErrorType(),
                'aws_error_message' => $e->getAwsErrorMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            // If this is an UnsupportedDocumentException, try converting PDF to images
            if ($e->getAwsErrorCode() === 'UnsupportedDocumentException' && str_ends_with($s3Path, '.pdf')) {
                Log::info('[TextractProvider] Unsupported PDF format detected, attempting conversion to images', [
                    's3_path' => $s3Path,
                ]);

                // Try to convert PDF to images and process them
                return TextractPdfImageProcessor::process($this->client, $this->bucket, $s3Path, $options);
            }

            // Ensure we always throw with a string message
            $errorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }
            throw new Exception($errorMessage);
        }
    }

    /**
     * Extract text from multi-page documents using async Textract API
     */
    protected function extractDocumentTextAsync(string $s3Path, array $featureTypes, array $options = []): array
    {
        try {
            // Start async document analysis job
            $result = $this->client->startDocumentAnalysis([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $this->bucket,
                        'Name' => $s3Path,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
            ]);

            $jobId = $result->get('JobId');

            Log::info('[TextractProvider] Async job started', [
                'job_id' => $jobId,
                's3_path' => $s3Path,
                'bucket' => $this->bucket,
            ]);

            // Poll for completion
            $maxAttempts = config('ai.ocr.providers.textract.max_polling_attempts', 60); // 60 attempts = 10 minutes at 10s intervals
            $pollingInterval = config('ai.ocr.providers.textract.polling_interval', 10); // 10 seconds
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                sleep($pollingInterval);
                $attempt++;

                $response = $this->client->getDocumentAnalysis([
                    'JobId' => $jobId,
                ]);

                $status = $response->get('JobStatus');

                Log::debug('[TextractProvider] Polling async job', [
                    'job_id' => $jobId,
                    'status' => $status,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                if ($status === 'SUCCEEDED') {
                    Log::info('[TextractProvider] Async job completed successfully', [
                        'job_id' => $jobId,
                        'attempts' => $attempt,
                        'total_time_seconds' => $attempt * $pollingInterval,
                    ]);

                    // Get all pages if there are multiple
                    $allBlocks = $response->get('Blocks');
                    $nextToken = $response->get('NextToken');

                    // If there are more pages, fetch them
                    while ($nextToken) {
                        $nextResponse = $this->client->getDocumentAnalysis([
                            'JobId' => $jobId,
                            'NextToken' => $nextToken,
                        ]);

                        $allBlocks = array_merge($allBlocks, $nextResponse->get('Blocks'));
                        $nextToken = $nextResponse->get('NextToken');

                        Log::debug('[TextractProvider] Fetched additional page', [
                            'job_id' => $jobId,
                            'total_blocks' => count($allBlocks),
                        ]);
                    }

                    // Parse the complete response
                    return TextractResponseParser::parseDocument([
                        'Blocks' => $allBlocks,
                        'DocumentMetadata' => $response->get('DocumentMetadata'),
                    ]);

                } elseif ($status === 'FAILED') {
                    $statusMessage = $response->get('StatusMessage') ?? 'Unknown error';
                    Log::error('[TextractProvider] Async job failed', [
                        'job_id' => $jobId,
                        'status_message' => $statusMessage,
                    ]);
                    throw new Exception("Textract async job failed: {$statusMessage}");

                } elseif ($status === 'PARTIAL_SUCCESS') {
                    Log::warning('[TextractProvider] Async job partially succeeded', [
                        'job_id' => $jobId,
                    ]);
                    // Continue to get results - partial success is still usable
                    $allBlocks = $response->get('Blocks');
                    return TextractResponseParser::parseDocument([
                        'Blocks' => $allBlocks,
                        'DocumentMetadata' => $response->get('DocumentMetadata'),
                    ]);
                }

                // Status is IN_PROGRESS, continue polling
            }

            // Max attempts reached
            $totalSeconds = $maxAttempts * $pollingInterval;
            throw new Exception("Textract async job timed out after {$maxAttempts} attempts ({$totalSeconds} seconds). Job ID: {$jobId}");

        } catch (AwsException $e) {
            Log::error('[TextractProvider] Async Textract error', [
                's3_path' => $s3Path,
                'bucket' => $this->bucket,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_message' => $e->getAwsErrorMessage(),
            ]);

            throw new Exception("Textract async API error: " . ($e->getAwsErrorMessage() ?: $e->getMessage()));
        }
    }

    // Parsing helpers moved to TextractResponseParser

    public function canHandle(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->getSupportedExtensions());
    }

    /**
     * Convert PDF to images and extract text from them
     */
    protected function extractFromConvertedPdf(string $s3Path, array $options = []): array
    {
        return TextractPdfImageProcessor::process($this->client, $this->bucket, $s3Path, $options);
    }

    protected function validateFile(string $filePath): array
    {
        return TextractFileValidator::validate($filePath, $this->getSupportedExtensions());
    }

    protected function validatePdfFile(string $filePath): array
    {
        return TextractFileValidator::validatePdf($filePath);
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
