<?php

namespace App\Services;

use App\Models\Document;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;

class DocumentAnalysisService
{
    private AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? AIServiceFactory::create();
    }

    /**
     * Analyze an existing Document model and return structured analysis data.
     * This is used by AnalyzeDocument job to update the existing row
     * without creating a new Document entry.
     */
    public function analyze(Document $document): array
    {
        $content = (string) ($document->content ?? '');

        if ($content === '') {
            throw new \Exception('No content available for analysis');
        }

        $result = $this->aiService->analyzeDocument($content, []);

        if (! is_array($result) || ! ($result['success'] ?? false)) {
            $error = is_array($result) ? ($result['error'] ?? 'Document analysis failed') : 'Document analysis failed';
            throw new \Exception($error);
        }

        return $result['data'] ?? [];
    }

    // Removed analyzeAndCreateDocument(): unused in current pipeline

    /** Generate document summary */
    public function generateSummary(string $content, int $maxLength = 200): string
    {
        return $this->aiService->generateSummary($content, $maxLength);
    }

    /** Suggest tags for document */
    public function suggestTags(string $content, int $maxTags = 5): array
    {
        return $this->aiService->suggestTags($content, $maxTags);
    }

    /** Classify document type */
    public function classifyDocument(string $content): string
    {
        return $this->aiService->classifyDocumentType($content);
    }

    /** Extract entities from document */
    public function extractEntities(string $content, array $types = []): array
    {
        return $this->aiService->extractEntities($content, $types);
    }

    // Removed determineCategory(): part of unused create flow

    // Removed attachTags(): part of unused create flow

    // Removed extractAndStoreDates(): part of unused create flow

    // Removed generateTitle(): part of unused create flow

    // Removed generateCategoryColor(): part of unused create flow

    // Removed reanalyzeDocument(): unused

    // Removed batchAnalyze(): unused
}
