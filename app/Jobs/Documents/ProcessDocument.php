<?php

namespace App\Jobs\Documents;

use App\Jobs\BaseJob;
use App\Models\Document;
use App\Models\File;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates a Document from a processed file and prepares it for analysis.
 *
 * Responsibilities:
 * - Ensure text extraction for documents
 * - Create Document row with metadata and searchable content
 * - Update File status/paths and queue AnalyzeDocument
 */
class ProcessDocument extends BaseJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Process Document';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            $metadata = $this->getMetadata();
            if (! $metadata) {
                throw new Exception('No metadata found for job');
            }

            $jobName = $metadata['jobName'] ?? 'unknown';

            Log::info("[ProcessDocument] [{$jobName}] Processing document", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_guid' => $metadata['fileGuid'],
                'file_id' => $metadata['fileId'],
            ]);

            $this->updateProgress(10);

            // Get services
            $storageService = app(StorageService::class);
            $textExtractionService = app(TextExtractionService::class);

            // Get the file record
            $file = File::find($metadata['fileId']);
            if (! $file) {
                throw new Exception("File not found: {$metadata['fileId']}");
            }

            // Extract text from document
            $extractedText = $metadata['extractedText'] ?? null;

            if (! $extractedText) {
                Log::debug("[ProcessDocument] [{$jobName}] Extracting text from document");

                $extractedText = $textExtractionService->extract(
                    $metadata['filePath'],
                    'document',
                    $metadata['fileGuid']
                );
            }

            $this->updateProgress(40);

            // Create archive version (PDF) if needed
            $archivePath = null;
            if ($file->fileExtension !== 'pdf') {
                // For non-PDF documents, we'll store the original as archive
                // In a full implementation, you might convert to PDF here
                Log::debug("[ProcessDocument] [{$jobName}] Non-PDF document, using original as archive");
                $archivePath = $metadata['s3OriginalPath'];
            } else {
                $archivePath = $metadata['s3OriginalPath'];
            }

            // Update file record with processed path
            $file->s3_processed_path = $archivePath;
            $file->status = 'processing';
            $file->save();

            $this->updateProgress(60);

            // Create document record
            DB::beginTransaction();

            try {
                $document = new Document;
                $document->file_id = $file->id;
                $document->user_id = $file->user_id;
                $document->title = $this->extractTitle($extractedText, $file->fileName);
                $document->description = $this->extractDescription($extractedText);
                $document->content = $extractedText; // Full text for search
                $document->document_type = $this->detectDocumentType($extractedText, $file->fileName);
                $document->extracted_text = $this->prepareExtractedText($extractedText);
                $document->language = $this->detectLanguage($extractedText);
                $document->document_date = $this->extractDocumentDate($extractedText) ?? now();
                $document->page_count = $this->estimatePageCount($extractedText);
                $document->metadata = [
                    'original_filename' => $file->fileName,
                    'mime_type' => $file->fileType,
                    'file_size' => $file->fileSize,
                    'page_count' => $this->estimatePageCount($extractedText),
                    'word_count' => str_word_count($extractedText),
                ];
                $document->save();

                // Update file status
                $file->status = 'completed';
                $file->save();

                DB::commit();

                // Cache document data for AI analysis job
                $documentData = [
                    'documentId' => $document->id,
                    'title' => $document->title,
                    'extractedText' => $extractedText,
                    'documentType' => $document->document_type,
                ];

                Cache::put("job.{$this->jobID}.documentMetaData", $documentData, now()->addHours(1));

                Log::debug("[ProcessDocument] [{$jobName}] Document created", [
                    'document_id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->document_type,
                ]);

                $this->updateProgress(90);

                // Make document searchable
                $document->searchable();

                $this->updateProgress(100);

                Log::info("[ProcessDocument] [{$jobName}] Document processed successfully", [
                    'job_id' => $this->jobID,
                    'task_id' => $this->uuid,
                    'document_id' => $document->id,
                ]);

                \App\Jobs\Documents\AnalyzeDocument::dispatch($this->jobID)
                    ->onQueue('documents');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('[ProcessDocument] Document processing failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update file status to failed
            try {
                if (isset($metadata['fileId'])) {
                    $file = File::find($metadata['fileId']);
                    if ($file) {
                        $file->status = 'failed';
                        $file->save();
                    }
                }
            } catch (Exception $statusError) {
                Log::warning('[ProcessDocument] Failed to update file status', [
                    'error' => $statusError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Extract title from text or filename
     */
    protected function extractTitle(string $text, string $filename): string
    {
        // Simple heuristic: First line or filename without extension
        $lines = explode("\n", trim($text));
        $firstLine = isset($lines[0]) ? trim($lines[0]) : '';

        if (strlen($firstLine) > 10 && strlen($firstLine) < 200) {
            return $firstLine;
        }

        // Fallback to filename
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * Extract description from text
     */
    protected function extractDescription(string $text): ?string
    {
        // Take first 500 characters as description
        $description = substr($text, 0, 500);

        if (strlen($description) > 50) {
            return $description.'...';
        }

        return null;
    }

    /**
     * Detect document type from content and filename
     */
    protected function detectDocumentType(string $text, string $filename): string
    {
        $lowerText = strtolower($text);
        $lowerFilename = strtolower($filename);

        // Simple keyword-based detection
        if (str_contains($lowerText, 'invoice') || str_contains($lowerFilename, 'invoice')) {
            return 'invoice';
        }
        if (str_contains($lowerText, 'contract') || str_contains($lowerFilename, 'contract')) {
            return 'contract';
        }
        if (str_contains($lowerText, 'report') || str_contains($lowerFilename, 'report')) {
            return 'report';
        }
        if (str_contains($lowerText, 'memo') || str_contains($lowerFilename, 'memo')) {
            return 'memo';
        }
        if (str_contains($lowerText, 'letter') || str_contains($lowerFilename, 'letter')) {
            return 'letter';
        }

        return 'other';
    }

    /**
     * Prepare extracted text for storage
     */
    protected function prepareExtractedText(string $text): array
    {
        // Split into chunks for better search performance
        $chunks = [];
        $chunkSize = 1000; // Characters per chunk

        $paragraphs = explode("\n\n", $text);
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            if (strlen($currentChunk.$paragraph) > $chunkSize && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $paragraph;
            } else {
                $currentChunk .= "\n\n".$paragraph;
            }
        }

        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Detect language from text
     */
    protected function detectLanguage(string $text): string
    {
        // Simple detection based on common words
        // In production, use a proper language detection library

        $norwegianWords = ['og', 'er', 'som', 'på', 'det', 'med', 'for', 'av', 'til', 'ikke'];
        $englishWords = ['the', 'and', 'is', 'in', 'it', 'with', 'for', 'of', 'to', 'not'];

        $lowerText = strtolower($text);
        $norwegianCount = 0;
        $englishCount = 0;

        foreach ($norwegianWords as $word) {
            $norwegianCount += substr_count($lowerText, ' '.$word.' ');
        }

        foreach ($englishWords as $word) {
            $englishCount += substr_count($lowerText, ' '.$word.' ');
        }

        if ($norwegianCount > $englishCount * 2) {
            return 'nb'; // Norwegian Bokmål
        }

        return 'en'; // Default to English
    }

    /**
     * Extract document date from text
     */
    protected function extractDocumentDate(string $text): ?\DateTime
    {
        // Simple date extraction using regex
        // Matches: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY
        $patterns = [
            '/(\d{4})-(\d{2})-(\d{2})/' => 'Y-m-d',
            '/(\d{2})\.(\d{2})\.(\d{4})/' => 'd.m.Y',
            '/(\d{2})\/(\d{2})\/(\d{4})/' => 'd/m/Y',
        ];

        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $text, $matches)) {
                try {
                    return \DateTime::createFromFormat($format, $matches[0]);
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Estimate page count from text
     */
    protected function estimatePageCount(string $text): int
    {
        // Rough estimate: 3000 characters per page
        $charCount = strlen($text);
        $pageCount = max(1, ceil($charCount / 3000));

        return $pageCount;
    }
}
