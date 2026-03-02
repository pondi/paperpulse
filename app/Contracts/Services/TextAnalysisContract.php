<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface TextAnalysisContract
{
    /**
     * Send a text prompt to the AI provider and get a structured JSON response.
     */
    public function analyze(string $prompt, ?array $responseSchema = null): array;

    /**
     * Get the name of the current provider.
     */
    public function getProviderName(): string;
}
