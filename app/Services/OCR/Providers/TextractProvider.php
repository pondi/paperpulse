<?php

namespace App\Services\OCR\Providers;

use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRService;
use App\Services\StorageService;
use Aws\Textract\TextractClient;
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
            'region' => config('ocr.textract.region', 'eu-central-1'),
            'credentials' => [
                'key' => config('ocr.textract.key'),
                'secret' => config('ocr.textract.secret'),
            ],
        ]);

        $this->bucket = config('filesystems.disks.textract.bucket');
    }

    public function extractText(string $filePath, string $fileType, string $fileGuid, array $options = []): OCRResult
    {
        $startTime = microtime(true);

        try {
            // Validate file before processing
            $validationResult = $this->validateFile($filePath);
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
                $result = match ($fileType) {
                    'receipt' => $this->extractReceiptText($textractPath, $options),
                    'document' => $this->extractDocumentText($textractPath, $options),
                    default => throw new Exception("Unknown file type: {$fileType}"),
                };

                $processingTime = (int) ((microtime(true) - $startTime) * 1000);

                return OCRResult::success(
                    text: $result['text'],
                    provider: $this->getProviderName(),
                    metadata: $result['metadata'],
                    confidence: $result['confidence'],
                    pages: $result['pages'] ?? [],
                    blocks: $result['blocks'] ?? [],
                    processingTime: $processingTime
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
            Log::error('[TextractProvider] Text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
            ]);

            return OCRResult::failure($e->getMessage(), $this->getProviderName());
        }
    }

    protected function extractReceiptText(string $s3Path, array $options = []): array
    {
        $result = $this->client->detectDocumentText([
            'Document' => [
                'S3Object' => [
                    'Bucket' => $this->bucket,
                    'Name' => $s3Path,
                ],
            ],
        ]);

        return $this->parseTextractResponse($result->toArray(), 'receipt');
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

            return $this->parseTextractDocumentResponse($result->toArray());
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

            // If this is an UnsupportedDocumentException, provide more specific error
            if ($e->getAwsErrorCode() === 'UnsupportedDocumentException') {
                throw new Exception('PDF format is not supported by Textract. The PDF might be encrypted, corrupted, or in an unsupported format. Try converting it to a standard PDF or using a different file.');
            }

            throw $e;
        }
    }

    protected function parseTextractResponse(array $result, string $type = 'basic'): array
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        $confidence = 0.0;
        $lineCount = 0;

        foreach ($blocks as $block) {
            if ($block['BlockType'] === 'LINE' && isset($block['Text'])) {
                $text .= $block['Text']."\n";
                $confidence += $block['Confidence'] ?? 0;
                $lineCount++;
            }
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'block_count' => count($blocks),
                'line_count' => $lineCount,
                'extraction_type' => $type,
                'textract_job_id' => $result['JobId'] ?? null,
            ],
            'confidence' => $lineCount > 0 ? $confidence / $lineCount / 100 : 0,
            'blocks' => $blocks,
        ];
    }

    protected function parseTextractDocumentResponse(array $result): array
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        $pages = [];
        $confidence = 0.0;
        $lineCount = 0;

        // Group blocks by page
        foreach ($blocks as $block) {
            $page = $block['Page'] ?? 1;
            if (! isset($pages[$page])) {
                $pages[$page] = [];
            }
            $pages[$page][] = $block;
        }

        // Process each page
        foreach ($pages as $pageNum => $pageBlocks) {
            if ($pageNum > 1) {
                $text .= "\n\n--- Page {$pageNum} ---\n\n";
            }

            // Sort blocks by vertical position
            usort($pageBlocks, function ($a, $b) {
                $aTop = $a['Geometry']['BoundingBox']['Top'] ?? 0;
                $bTop = $b['Geometry']['BoundingBox']['Top'] ?? 0;

                return $aTop <=> $bTop;
            });

            foreach ($pageBlocks as $block) {
                if ($block['BlockType'] === 'LINE' && isset($block['Text'])) {
                    $text .= $block['Text']."\n";
                    $confidence += $block['Confidence'] ?? 0;
                    $lineCount++;
                } elseif ($block['BlockType'] === 'TABLE') {
                    $text .= $this->parseTable($block, $blocks)."\n";
                }
            }
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'page_count' => count($pages),
                'block_count' => count($blocks),
                'line_count' => $lineCount,
                'extraction_type' => 'document_analysis',
            ],
            'confidence' => $lineCount > 0 ? $confidence / $lineCount / 100 : 0,
            'pages' => array_keys($pages),
            'blocks' => $blocks,
        ];
    }

    protected function parseTable(array $tableBlock, array $allBlocks): string
    {
        // Simplified table parsing - can be enhanced later
        return '[TABLE CONTENT]';
    }

    public function canHandle(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->getSupportedExtensions());
    }

    protected function validateFile(string $filePath): array
    {
        // Check if file exists
        if (! file_exists($filePath)) {
            return ['valid' => false, 'error' => 'File does not exist'];
        }

        // Check file size (Textract limit is 10MB for single-page, 512MB for multi-page)
        $fileSize = filesize($filePath);
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes

        if ($fileSize > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit for Textract'];
        }

        if ($fileSize === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        // Check file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $supportedExtensions = $this->getSupportedExtensions();

        if (! in_array($extension, $supportedExtensions)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format '{$extension}'. Supported formats: ".implode(', ', $supportedExtensions),
            ];
        }

        // Validate MIME type to prevent files with wrong extensions
        $mimeType = mime_content_type($filePath);
        $expectedMimeTypes = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'tiff' => ['image/tiff', 'image/tif'],
            'tif' => ['image/tiff', 'image/tif'],
        ];

        if (isset($expectedMimeTypes[$extension]) && ! in_array($mimeType, $expectedMimeTypes[$extension])) {
            return [
                'valid' => false,
                'error' => "File MIME type '{$mimeType}' doesn't match extension '{$extension}'. File may be corrupted or have wrong extension.",
            ];
        }

        // Basic file integrity check
        if ($extension === 'pdf') {
            $pdfValidation = $this->validatePdfFile($filePath);
            if (! $pdfValidation['valid']) {
                return $pdfValidation;
            }
        } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'tiff', 'tif'])) {
            // Try to get image info to validate image files
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'Invalid or corrupted image file'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    protected function validatePdfFile(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return ['valid' => false, 'error' => 'Cannot open PDF file'];
        }

        // Read first 1024 bytes for analysis
        $header = fread($handle, 1024);
        $fileSize = filesize($filePath);

        // Basic PDF header check
        if (substr($header, 0, 4) !== '%PDF') {
            fclose($handle);

            return ['valid' => false, 'error' => 'Invalid PDF file - missing PDF header'];
        }

        // Extract PDF version
        if (preg_match('/%PDF-(\d+\.\d+)/', $header, $matches)) {
            $pdfVersion = $matches[1];
            Log::info('[TextractProvider] PDF validation details', [
                'file_path' => basename($filePath),
                'pdf_version' => $pdfVersion,
                'file_size' => $fileSize,
            ]);
        }

        // Check for encryption
        if (strpos($header, '/Encrypt') !== false) {
            fclose($handle);

            return ['valid' => false, 'error' => 'PDF file is encrypted - Textract cannot process encrypted PDFs'];
        }

        // Read trailer to check for corruption
        fseek($handle, max(0, $fileSize - 1024));
        $trailer = fread($handle, 1024);
        fclose($handle);

        // Check for proper PDF ending
        if (strpos($trailer, '%%EOF') === false) {
            Log::warning('[TextractProvider] PDF may be corrupted - missing %%EOF marker', [
                'file_path' => basename($filePath),
                'trailer_sample' => substr($trailer, -100),
            ]);
            // Don't fail for this as some PDFs might still work
        }

        // Check for potential issues that Textract might reject
        $issues = [];

        // Check for forms/annotations that might cause issues
        if (strpos($header, '/AcroForm') !== false) {
            $issues[] = 'Contains AcroForms (interactive forms)';
        }

        if (strpos($header, '/Annot') !== false) {
            $issues[] = 'Contains annotations';
        }

        if (! empty($issues)) {
            Log::info('[TextractProvider] PDF contains potentially problematic features', [
                'file_path' => basename($filePath),
                'issues' => $issues,
            ]);
        }

        return ['valid' => true, 'error' => null];
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
