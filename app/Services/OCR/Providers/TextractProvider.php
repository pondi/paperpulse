<?php

namespace App\Services\OCR\Providers;

use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRService;
use App\Services\OCR\Textract\TextractFileValidator;
use App\Services\OCR\Textract\TextractPdfImageProcessor;
use App\Services\OCR\Textract\TextractResponseParser;
use App\Services\StorageService;
use Aws\Textract\TextractClient;
// PDF handling is delegated to TextractPdfImageProcessor
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TextractProvider implements OCRService
{
    protected TextractClient $client;

    protected string $bucket;

    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
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
                } catch (\Throwable $matchError) {
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
            if ($e instanceof \Aws\Exception\AwsException) {
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
        Log::info('[TextractProvider] Calling Textract AnalyzeDocument', [
            's3_path' => $s3Path,
            'bucket' => $this->bucket,
            'file_size' => $fileSize,
            'feature_types' => $featureTypes,
        ]);

        // Check if file size is within Textract limits
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception("File size ({$fileSize} bytes) exceeds Textract limit of 10MB");
        }

        if ($fileSize === 0) {
            throw new Exception('File is empty in S3 bucket');
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
        } catch (\Aws\Exception\AwsException $e) {
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
