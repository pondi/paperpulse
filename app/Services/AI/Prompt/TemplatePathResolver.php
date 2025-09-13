<?php

namespace App\Services\AI\Prompt;

use Illuminate\Support\Facades\View;

class TemplatePathResolver
{
    public static function resolve(string $templateName, array $defaults): string
    {
        $customPath = "ai.prompts.custom.{$templateName}";
        if (View::exists($customPath)) {
            return $customPath;
        }

        return $defaults[$templateName] ?? "ai.prompts.{$templateName}";
    }
}

