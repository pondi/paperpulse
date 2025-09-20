<?php

namespace App\Services\AI\Prompt\Schema;

/**
 * Resolves JSON Schemas associated with prompt templates.
 */
class PromptSchemaResolver
{
    /** Resolve schema for template name, with options. */
    public static function forTemplate(string $templateName, array $options = []): array
    {
        return match ($templateName) {
            'receipt' => ReceiptPromptSchemaProvider::schema($options),
            'document' => DocumentPromptSchemaProvider::schema($options),
            default => DefaultPromptSchemaProvider::schema($templateName),
        };
    }
}
