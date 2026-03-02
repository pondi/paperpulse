<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\Services\TextAnalysisContract;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Support\Facades\Log;
use OpenAI;

class TextAnalysisService implements TextAnalysisContract
{
    protected string $provider;

    public function __construct(
        protected ?GeminiProvider $geminiProvider = null
    ) {
        $this->provider = config('ai.text_analysis_provider', 'gemini');
    }

    public function analyze(string $prompt, ?array $responseSchema = null): array
    {
        return match ($this->provider) {
            'gemini' => $this->analyzeWithGemini($prompt, $responseSchema),
            'openai' => $this->analyzeWithOpenAI($prompt, $responseSchema),
            default => throw new Exception("Unsupported text analysis provider: {$this->provider}"),
        };
    }

    public function getProviderName(): string
    {
        return $this->provider;
    }

    protected function analyzeWithGemini(string $prompt, ?array $responseSchema): array
    {
        if (! $this->geminiProvider) {
            throw new Exception('GeminiProvider not available');
        }

        return $this->geminiProvider->generateText($prompt, $responseSchema);
    }

    protected function analyzeWithOpenAI(string $prompt, ?array $responseSchema): array
    {
        $model = config('ai.models.default', 'gpt-5.2');

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a data analysis assistant. Always respond with valid JSON only. No markdown, no explanations outside the JSON.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $params = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => 4096,
        ];

        if ($responseSchema !== null) {
            $params['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'analysis_response',
                    'schema' => $responseSchema,
                    'strict' => (bool) config('ai.options.strict_json_schema', false),
                ],
            ];
        } else {
            $params['response_format'] = ['type' => 'json_object'];
        }

        Log::info('[TextAnalysisService] Sending request to OpenAI', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'has_schema' => $responseSchema !== null,
        ]);

        $response = OpenAI::chat()->create($params);

        $content = $response->choices[0]->message->content ?? '';

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse OpenAI JSON response: '.json_last_error_msg());
        }

        return $decoded;
    }
}
