<?php

namespace App\Services\AI\Prompt;

use Illuminate\Support\Facades\View;

/**
 * Resolves prompt Blade view paths, allowing custom overrides.
 */
class TemplatePathResolver
{
    /**
     * Resolve a template path by checking for custom override first.
     */
    public static function resolve(string $templateName, array $defaults): string
    {
        $customPath = "ai.prompts.custom.{$templateName}";
        if (View::exists($customPath)) {
            return $customPath;
        }

        return $defaults[$templateName] ?? "ai.prompts.{$templateName}";
    }
}
