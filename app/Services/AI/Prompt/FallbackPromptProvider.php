<?php

namespace App\Services\AI\Prompt;

/**
 * Provides minimal fallback prompts when template rendering fails.
 */
class FallbackPromptProvider
{
    /** Get a generic fallback prompt for a template key. */
    public static function forTemplate(string $templateName): string
    {
        $fallbacks = [
            'receipt' => 'Analyze this Norwegian receipt and extract structured data in JSON format.',
            'document' => 'Analyze this document and extract structured metadata in JSON format.',
            'merchant' => 'Extract merchant information from this text in JSON format.',
            'line_items' => 'Extract line items from this receipt in JSON format.',
            'summary' => 'Provide a concise summary of this content.',
            'classification' => 'Classify this document type.',
            'tags' => 'Extract relevant tags from this content.',
            'entities' => 'Extract named entities from this text.',
        ];

        return $fallbacks[$templateName] ?? 'Analyze this content and provide structured information.';
    }
}
