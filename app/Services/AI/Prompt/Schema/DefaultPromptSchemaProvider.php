<?php

namespace App\Services\AI\Prompt\Schema;

/**
 * Fallback schema provider for templates without dedicated schemas.
 */
class DefaultPromptSchemaProvider
{
    /** Return a minimal schema for generic prompts. */
    public static function schema(string $templateName): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
            ],
            'required' => ['result'],
        ];
    }
}
