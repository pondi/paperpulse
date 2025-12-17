<?php

namespace App\Services\AI\Prompt;

/**
 * Supplies per-template default options merged with caller overrides.
 */
class TemplateOptionsProvider
{
    /**
     * Get default options for a template and merge with user overrides.
     */
    public static function forTemplate(string $templateName, array $userOptions = []): array
    {
        $defaults = [
            'receipt' => [
                'temperature' => 0.1,
                'max_completion_tokens' => 2048,
                'response_format' => 'json_schema',
            ],
            'document' => [
                'temperature' => 0.2,
                'max_completion_tokens' => 3000,
                'response_format' => 'json_schema',
            ],
            'merchant' => [
                'temperature' => 0.1,
                'max_completion_tokens' => 300,
                'response_format' => 'json_object',
            ],
            'summary' => [
                'temperature' => 0.3,
                'max_completion_tokens' => 300,
            ],
        ];

        return array_merge($defaults[$templateName] ?? [], $userOptions);
    }
}
