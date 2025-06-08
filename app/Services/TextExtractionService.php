<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class TextExtractionService
{
    protected $textractClient;
    protected $textractBucket;
    protected $storageService;
    
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
        $this->initializeTextract();
    }
    
    /**
     * Initialize AWS Textract client
     */
    protected function initializeTextract(): void
    {
        $this->textractClient = new TextractClient([
            'version' => 'latest',
            'region' => env('TEXTRACT_REGION', 'eu-central-1'),
            'credentials' => [
                'key' => env('TEXTRACT_KEY'),
                'secret' => env('TEXTRACT_SECRET'),
            ],
        ]);
        
        $this->textractBucket = env('TEXTRACT_BUCKET');
    }
    
    /**
     * Extract text from a file
     * 
     * @param string $filePath Path to the file
     * @param string $fileType 'receipt' or 'document'
     * @param string $fileGuid Unique file identifier
     * @return string Extracted text
     */
    public function extract(string $filePath, string $fileType, string $fileGuid): string
    {
        try {
            // Check cache first
            $cacheKey = "text_extraction.{$fileGuid}";
            $cachedText = Cache::get($cacheKey);
            
            if ($cachedText !== null) {
                Log::debug('[TextExtractionService] Using cached text', [
                    'file_guid' => $fileGuid,
                ]);
                return $cachedText;
            }
            
            // Determine extraction method based on file type
            $text = match($fileType) {
                'receipt' => $this->extractReceiptText($filePath, $fileGuid),
                'document' => $this->extractDocumentText($filePath, $fileGuid),
                default => throw new Exception("Unknown file type: {$fileType}"),
            };
            
            // Cache the extracted text
            Cache::put($cacheKey, $text, now()->addDays(7));
            
            Log::info('[TextExtractionService] Text extracted successfully', [
                'file_guid' => $fileGuid,
                'file_type' => $fileType,
                'text_length' => strlen($text),
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
     * Extract text from a receipt using single-page dense text mode
     * 
     * @param string $filePath Path to the file
     * @param string $fileGuid Unique file identifier
     * @return string Extracted text
     */
    protected function extractReceiptText(string $filePath, string $fileGuid): string
    {
        try {
            // Read file content
            $fileContent = file_get_contents($filePath);
            
            if (!$fileContent) {
                throw new Exception('Could not read file content');
            }
            
            // Upload to Textract bucket temporarily
            $textractPath = "temp/{$fileGuid}/" . basename($filePath);
            $textractDisk = Storage::disk('textract');
            $textractDisk->put($textractPath, $fileContent);
            
            try {
                // Use DetectDocumentText for receipts (optimized for dense text)
                $result = $this->textractClient->detectDocumentText([
                    'Document' => [
                        'S3Object' => [
                            'Bucket' => $this->textractBucket,
                            'Name' => $textractPath,
                        ],
                    ],
                ]);
                
                // Extract text from Textract response
                $text = $this->parseTextractResponse($result);
                
                return $text;
            } finally {
                // Clean up temporary file
                $textractDisk->delete($textractPath);
            }
        } catch (Exception $e) {
            Log::error('[TextExtractionService] Receipt text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            
            // Fallback to basic PDF text extraction if available
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
                return $this->extractPdfTextFallback($filePath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Extract text from a document using multi-page layout analysis
     * 
     * @param string $filePath Path to the file
     * @param string $fileGuid Unique file identifier
     * @return string Extracted text
     */
    protected function extractDocumentText(string $filePath, string $fileGuid): string
    {
        try {
            // For PDFs, try native extraction first
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
                $text = $this->extractPdfTextFallback($filePath);
                if (!empty(trim($text))) {
                    Log::debug('[TextExtractionService] Using native PDF text extraction', [
                        'file_guid' => $fileGuid,
                    ]);
                    return $text;
                }
            }
            
            // Read file content
            $fileContent = file_get_contents($filePath);
            
            if (!$fileContent) {
                throw new Exception('Could not read file content');
            }
            
            // Upload to Textract bucket temporarily
            $textractPath = "temp/{$fileGuid}/" . basename($filePath);
            $textractDisk = Storage::disk('textract');
            $textractDisk->put($textractPath, $fileContent);
            
            try {
                // Use AnalyzeDocument for documents (preserves layout)
                $result = $this->textractClient->analyzeDocument([
                    'Document' => [
                        'S3Object' => [
                            'Bucket' => $this->textractBucket,
                            'Name' => $textractPath,
                        ],
                    ],
                    'FeatureTypes' => ['LAYOUT', 'TABLES', 'FORMS'],
                ]);
                
                // Extract text with layout preservation
                $text = $this->parseTextractDocumentResponse($result);
                
                return $text;
            } finally {
                // Clean up temporary file
                $textractDisk->delete($textractPath);
            }
        } catch (Exception $e) {
            Log::error('[TextExtractionService] Document text extraction failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            
            // Fallback to basic extraction
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
                return $this->extractPdfTextFallback($filePath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Parse Textract response for basic text extraction
     * 
     * @param array $result Textract response
     * @return string Extracted text
     */
    protected function parseTextractResponse(array $result): string
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        
        foreach ($blocks as $block) {
            if ($block['BlockType'] === 'LINE' && isset($block['Text'])) {
                $text .= $block['Text'] . "\n";
            }
        }
        
        return trim($text);
    }
    
    /**
     * Parse Textract response for document with layout preservation
     * 
     * @param array $result Textract response
     * @return string Extracted text with layout
     */
    protected function parseTextractDocumentResponse(array $result): string
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        $pages = [];
        
        // Group blocks by page
        foreach ($blocks as $block) {
            $page = $block['Page'] ?? 1;
            if (!isset($pages[$page])) {
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
            usort($pageBlocks, function($a, $b) {
                $aTop = $a['Geometry']['BoundingBox']['Top'] ?? 0;
                $bTop = $b['Geometry']['BoundingBox']['Top'] ?? 0;
                return $aTop <=> $bTop;
            });
            
            foreach ($pageBlocks as $block) {
                if ($block['BlockType'] === 'LINE' && isset($block['Text'])) {
                    $text .= $block['Text'] . "\n";
                } elseif ($block['BlockType'] === 'TABLE') {
                    $text .= $this->parseTable($block, $blocks) . "\n";
                }
            }
        }
        
        return trim($text);
    }
    
    /**
     * Parse table from Textract blocks
     * 
     * @param array $tableBlock Table block
     * @param array $allBlocks All blocks for reference
     * @return string Table as text
     */
    protected function parseTable(array $tableBlock, array $allBlocks): string
    {
        // This is a simplified table parser
        // In production, you would build the full table structure
        return "[TABLE CONTENT]\n";
    }
    
    /**
     * Fallback PDF text extraction using PHP
     * 
     * @param string $filePath Path to PDF file
     * @return string Extracted text
     */
    protected function extractPdfTextFallback(string $filePath): string
    {
        try {
            // Use smalot/pdfparser if available
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
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
     * 
     * @param string $fileGuid File GUID
     * @return void
     */
    public function clearCache(string $fileGuid): void
    {
        $cacheKey = "text_extraction.{$fileGuid}";
        Cache::forget($cacheKey);
        
        Log::debug('[TextExtractionService] Cache cleared', [
            'file_guid' => $fileGuid,
        ]);
    }
}