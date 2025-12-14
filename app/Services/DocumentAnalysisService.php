<?php

namespace App\Services;

use App\Models\Document;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use Exception;
use Illuminate\Support\Facades\Log;

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
     */
    public function analyze(Document $document): array
    {
        $content = (string) ($document->content ?? '');

        if ($content === '') {
            throw new Exception('No content available for analysis');
        }

        // Smart token limiting for large documents
        // OpenAI charges per token, so we need to be smart about what we send
        $content = $this->prepareContentForAnalysis($content);

        $result = $this->aiService->analyzeDocument($content, []);

        if (! is_array($result) || ! ($result['success'] ?? false)) {
            $error = is_array($result) ? ($result['error'] ?? 'Document analysis failed') : 'Document analysis failed';
            throw new Exception($error);
        }

        return $result['data'] ?? [];
    }

    /**
     * Prepare document content for AI analysis with smart token limiting.
     *
     * For large documents, we extract key sections instead of sending the entire content:
     * - First N characters (beginning of document)
     * - Last N characters (end of document)
     * - This gives AI enough context without wasting tokens on full content
     */
    protected function prepareContentForAnalysis(string $content): string
    {
        // Max characters to send (roughly 8000 tokens = ~32,000 characters at 4 chars/token)
        // We use 16,000 chars as a safe limit to leave room for system prompts
        $maxChars = config('ai.document_analysis.max_chars', 16000);

        // If content is already small enough, send it all
        if (mb_strlen($content) <= $maxChars) {
            return $content;
        }

        // For large documents, take beginning and end
        $halfMax = (int) ($maxChars / 2);

        $beginning = mb_substr($content, 0, $halfMax);
        $end = mb_substr($content, -$halfMax);

        $skippedChars = mb_strlen($content) - ($halfMax * 2);
        $skippedWords = (int) ($skippedChars / 5); // Rough estimate

        // Add a marker so AI knows content was truncated
        $truncationNote = "\n\n[... {$skippedWords} words omitted from middle of document ...]\n\n";

        $preparedContent = $beginning . $truncationNote . $end;

        Log::info('[DocumentAnalysisService] Content truncated for AI analysis', [
            'original_length' => mb_strlen($content),
            'prepared_length' => mb_strlen($preparedContent),
            'skipped_chars' => $skippedChars,
            'max_chars_limit' => $maxChars,
        ]);

        return $preparedContent;
    }

    /**
     * Generate a natural-language summary for the document content.
     */
    public function generateSummary(string $content, int $maxLength = 200): string
    {
        return $this->aiService->generateSummary($content, $maxLength);
    }

    /**
     * Suggest tags based on document content.
     */
    public function suggestTags(string $content, int $maxTags = 5): array
    {
        return $this->aiService->suggestTags($content, $maxTags);
    }

    /**
     * Classify document type (e.g., invoice, report).
     */
    public function classifyDocument(string $content): string
    {
        return $this->aiService->classifyDocumentType($content);
    }

    /**
     * Extract entities from document content.
     */
    public function extractEntities(string $content, array $types = []): array
    {
        return $this->aiService->extractEntities($content, $types);
    }
}
