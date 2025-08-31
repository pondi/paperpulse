<?php

namespace App\Services\AI;

interface AIService
{
    /**
     * Analyze a receipt and extract structured data
     *
     * @param  string  $content  The OCR text content
     * @param  array  $options  Additional options for the analysis
     * @return array Structured receipt data
     */
    public function analyzeReceipt(string $content, array $options = []): array;

    /**
     * Analyze a document and extract metadata
     *
     * @param  string  $content  The document text content
     * @param  array  $options  Additional options for the analysis
     * @return array Document metadata and analysis
     */
    public function analyzeDocument(string $content, array $options = []): array;

    /**
     * Extract merchant information from receipt text
     *
     * @param  string  $content  The receipt text
     * @return array Merchant data
     */
    public function extractMerchant(string $content): array;

    /**
     * Generate a summary for a document
     *
     * @param  string  $content  The document content
     * @param  int  $maxLength  Maximum summary length
     * @return string Document summary
     */
    public function generateSummary(string $content, int $maxLength = 200): string;

    /**
     * Suggest tags for a document
     *
     * @param  string  $content  The document content
     * @param  int  $maxTags  Maximum number of tags
     * @return array Suggested tags
     */
    public function suggestTags(string $content, int $maxTags = 5): array;

    /**
     * Classify document type
     *
     * @param  string  $content  The document content
     * @return string Document type classification
     */
    public function classifyDocumentType(string $content): string;

    /**
     * Extract entities from text
     *
     * @param  string  $content  The text content
     * @param  array  $types  Entity types to extract
     * @return array Extracted entities
     */
    public function extractEntities(string $content, array $types = []): array;

    /**
     * Get the provider name
     */
    public function getProviderName(): string;
}
