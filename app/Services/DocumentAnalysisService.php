<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Tag;
use App\Models\Category;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentAnalysisService
{
    private AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? AIServiceFactory::create();
    }

    /**
     * Analyze document content and create document with metadata
     *
     * @param string $content Document text content
     * @param int $fileId Associated file ID
     * @param int $userId User ID
     * @param array $options Additional options
     * @return Document
     */
    public function analyzeAndCreateDocument(string $content, int $fileId, int $userId, array $options = []): Document
    {
        Log::info('Starting document analysis', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'content_length' => strlen($content)
        ]);

        try {
            // Analyze document using AI
            $analysis = $this->aiService->analyzeDocument($content, $options);

            if (!$analysis['success']) {
                throw new \Exception($analysis['error'] ?? 'Document analysis failed');
            }

            $data = $analysis['data'];

            DB::beginTransaction();

            // Determine or create category
            $category = $this->determineCategory($data['document_type'] ?? 'other', $userId);

            // Create document
            $document = Document::create([
                'user_id' => $userId,
                'file_id' => $fileId,
                'category_id' => $category?->id,
                'title' => $data['title'] ?? $this->generateTitle($content),
                'document_type' => $data['document_type'] ?? 'other',
                'summary' => $data['summary'] ?? $this->aiService->generateSummary($content),
                'content' => $content,
                'language' => $data['language'] ?? 'en',
                'entities' => $data['entities'] ?? [],
                'metadata' => array_merge($analysis, $options['metadata'] ?? []),
                'status' => 'processed',
                'processed_at' => Carbon::now()
            ]);

            // Create and attach tags
            $this->attachTags($document, $data['tags'] ?? [], $userId);

            // Extract and store key dates
            $this->extractAndStoreDates($document, $data['entities']['dates'] ?? []);

            DB::commit();

            Log::info('Document analysis completed', [
                'document_id' => $document->id,
                'category_id' => $category?->id,
                'tag_count' => count($data['tags'] ?? [])
            ]);

            return $document;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document analysis failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId
            ]);
            throw $e;
        }
    }

    /**
     * Generate document summary
     *
     * @param string $content
     * @param int $maxLength
     * @return string
     */
    public function generateSummary(string $content, int $maxLength = 200): string
    {
        return $this->aiService->generateSummary($content, $maxLength);
    }

    /**
     * Suggest tags for document
     *
     * @param string $content
     * @param int $maxTags
     * @return array
     */
    public function suggestTags(string $content, int $maxTags = 5): array
    {
        return $this->aiService->suggestTags($content, $maxTags);
    }

    /**
     * Classify document type
     *
     * @param string $content
     * @return string
     */
    public function classifyDocument(string $content): string
    {
        return $this->aiService->classifyDocumentType($content);
    }

    /**
     * Extract entities from document
     *
     * @param string $content
     * @param array $types
     * @return array
     */
    public function extractEntities(string $content, array $types = []): array
    {
        return $this->aiService->extractEntities($content, $types);
    }

    /**
     * Determine or create category based on document type
     *
     * @param string $documentType
     * @param int $userId
     * @return Category|null
     */
    private function determineCategory(string $documentType, int $userId): ?Category
    {
        // Map document types to categories
        $categoryMap = [
            'invoice' => 'Invoices',
            'contract' => 'Contracts',
            'report' => 'Reports',
            'letter' => 'Correspondence',
            'memo' => 'Memos',
            'presentation' => 'Presentations',
            'spreadsheet' => 'Spreadsheets',
            'email' => 'Emails',
            'legal' => 'Legal Documents',
            'financial' => 'Financial Documents',
            'technical' => 'Technical Documents'
        ];

        $categoryName = $categoryMap[$documentType] ?? 'General';

        // Find or create category
        return Category::firstOrCreate(
            [
                'name' => $categoryName,
                'user_id' => $userId
            ],
            [
                'description' => "Auto-created category for {$documentType} documents",
                'color' => $this->generateCategoryColor($categoryName)
            ]
        );
    }

    /**
     * Attach tags to document
     *
     * @param Document $document
     * @param array $tagNames
     * @param int $userId
     */
    private function attachTags(Document $document, array $tagNames, int $userId): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                [
                    'name' => Str::slug($tagName),
                    'user_id' => $userId
                ],
                [
                    'display_name' => $tagName
                ]
            );

            $tagIds[] = $tag->id;
        }

        $document->tags()->sync($tagIds);
    }

    /**
     * Extract and store important dates from document
     *
     * @param Document $document
     * @param array $dates
     */
    private function extractAndStoreDates(Document $document, array $dates): void
    {
        $metadata = $document->metadata ?? [];
        $parsedDates = [];

        foreach ($dates as $dateStr) {
            try {
                $date = Carbon::parse($dateStr);
                $parsedDates[] = [
                    'original' => $dateStr,
                    'parsed' => $date->toDateString(),
                    'timestamp' => $date->timestamp
                ];
            } catch (\Exception $e) {
                Log::debug('Failed to parse date', ['date' => $dateStr]);
            }
        }

        if (!empty($parsedDates)) {
            $metadata['extracted_dates'] = $parsedDates;
            
            // Set document date to the earliest extracted date
            usort($parsedDates, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
            $document->document_date = $parsedDates[0]['parsed'];
        }

        $document->metadata = $metadata;
        $document->save();
    }

    /**
     * Generate title from content if not provided
     *
     * @param string $content
     * @return string
     */
    private function generateTitle(string $content): string
    {
        // Take first line or first 100 characters
        $lines = explode("\n", trim($content));
        $title = $lines[0] ?? '';

        if (strlen($title) > 100) {
            $title = Str::limit($title, 100);
        }

        return $title ?: 'Untitled Document';
    }

    /**
     * Generate color for category
     *
     * @param string $name
     * @return string
     */
    private function generateCategoryColor(string $name): string
    {
        $colors = [
            '#EF4444', '#F59E0B', '#10B981', '#3B82F6',
            '#6366F1', '#8B5CF6', '#EC4899', '#14B8A6'
        ];

        // Use name hash to consistently assign same color
        $hash = crc32($name);
        return $colors[$hash % count($colors)];
    }

    /**
     * Reanalyze an existing document
     *
     * @param Document $document
     * @return Document
     */
    public function reanalyzeDocument(Document $document): Document
    {
        if (!$document->content) {
            throw new \Exception('No content available for reanalysis');
        }

        // Clear existing tags
        $document->tags()->detach();

        // Reanalyze
        return $this->analyzeAndCreateDocument(
            $document->content,
            $document->file_id,
            $document->user_id,
            ['reanalysis' => true]
        );
    }

    /**
     * Batch analyze multiple documents
     *
     * @param array $documents Array of [content, fileId, userId]
     * @return array
     */
    public function batchAnalyze(array $documents): array
    {
        $results = [];

        foreach ($documents as $doc) {
            try {
                $results[] = [
                    'success' => true,
                    'document' => $this->analyzeAndCreateDocument(
                        $doc['content'],
                        $doc['fileId'],
                        $doc['userId'],
                        $doc['options'] ?? []
                    )
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'fileId' => $doc['fileId']
                ];
            }
        }

        return $results;
    }
}