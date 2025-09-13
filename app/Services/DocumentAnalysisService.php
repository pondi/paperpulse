<?php

namespace App\Services;

use App\Models\Document;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;

/**
 * Provides AI-powered analysis utilities for Document content.
 *
 * Wraps the configured AIService to analyze, summarize, tag, classify,
 * and extract entities for documents.
 */
class DocumentAnalysisService
{
    private AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? AIServiceFactory::create();
    }

    /**
     * Analyze an existing Document and return structured analysis data.
     * Used by AnalyzeDocument job to update the document in-place.
     *
     * @param Document $document
     * @return array
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

    /**
     * Generate a natural-language summary for the document content.
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
     * Suggest tags based on document content.
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
     * Classify document type (e.g., invoice, report).
     *
     * @param string $content
     * @return string
     */
    public function classifyDocument(string $content): string
    {
        return $this->aiService->classifyDocumentType($content);
    }

    /**
     * Extract entities from document content.
     *
     * @param string $content
     * @param array $types
     * @return array
     */
    public function extractEntities(string $content, array $types = []): array
    {
        return $this->aiService->extractEntities($content, $types);
    }
}
