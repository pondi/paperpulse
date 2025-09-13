<?php

namespace App\Services\AI\Prompt\Schema;

class PromptSchemaResolver
{
    public static function forTemplate(string $templateName, array $options = []): array
    {
        return match ($templateName) {
            'receipt' => ReceiptPromptSchemaProvider::schema($options),
            'document' => DocumentPromptSchemaProvider::schema($options),
            default => DefaultPromptSchemaProvider::schema($templateName),
        };
    }
}

