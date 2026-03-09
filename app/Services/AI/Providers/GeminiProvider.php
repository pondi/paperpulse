<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Exceptions\GeminiApiException;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider
{
    protected GeminiResponseParser $responseParser;

    protected GeminiFileAnalyzer $fileAnalyzer;

    public function __construct(
        ?GeminiResponseParser $responseParser = null,
        ?GeminiFileAnalyzer $fileAnalyzer = null,
    ) {
        $this->responseParser = $responseParser ?? new GeminiResponseParser;
        $this->fileAnalyzer = $fileAnalyzer ?? new GeminiFileAnalyzer;
    }

    /**
     * Resolve the Gemini model name and API key from config.
     *
     * @return array{string, string} [$model, $apiKey]
     */
    protected function resolveModelAndKey(): array
    {
        $model = config('ai.providers.gemini.model', 'gemini-2.0-flash');
        $apiKey = config('ai.providers.gemini.api_key');

        if (empty($apiKey)) {
            throw new GeminiApiException(
                'Missing Gemini API key.',
                GeminiApiException::CODE_API_ERROR,
                false,
                ['provider' => 'gemini']
            );
        }

        return [$model, $apiKey];
    }

    /**
     * Build the generationConfig payload for a Gemini request.
     */
    protected function buildGenerationConfig(float $temperature, ?array $responseSchema): array
    {
        $config = [
            'temperature' => $temperature,
            'responseMimeType' => 'application/json',
        ];

        if ($responseSchema !== null) {
            $config['responseJsonSchema'] = $responseSchema;
        }

        return $config;
    }

    /**
     * Send a request to the Gemini API and return the parsed response.
     *
     * @return array{body: array, text: string}
     */
    protected function sendGeminiRequest(array $payload, string $model, string $apiKey): array
    {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $apiKey
        );

        try {
            $response = Http::timeout((int) config('ai.providers.gemini.timeout', 90))
                ->asJson()
                ->post($endpoint, $payload);
        } catch (Exception $e) {
            $this->handleRequestException($e);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $errorCode = $status === 429 ? GeminiApiException::CODE_RATE_LIMIT : GeminiApiException::CODE_API_ERROR;
            $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);

            throw new GeminiApiException(
                'Gemini API error: '.$response->body(),
                $errorCode,
                $retryable,
                ['status' => $status, 'body' => $response->body()]
            );
        }

        $responseBody = $response->json();
        $textResponse = $this->responseParser->extractTextResponse($responseBody);

        return ['body' => $responseBody, 'text' => $textResponse];
    }

    /**
     * Analyze a file with Gemini.
     */
    public function analyzeFile(string $filePath, array $schema, ?string $prompt = null): array
    {
        $this->fileAnalyzer->ensureSupported($filePath);

        [$model, $apiKey] = $this->resolveModelAndKey();

        $fileSize = filesize($filePath) ?: 0;
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $textContext = $this->fileAnalyzer->buildTextContext($filePath, $mime, $extension);
        $largeFileContext = $this->fileAnalyzer->buildLargeFileContext($filePath, $mime, $fileSize, $textContext);
        $promptText = $this->fileAnalyzer->buildPrompt($prompt, $schema, $textContext, $largeFileContext);

        Log::info('[GeminiProvider] Analyzing file', [
            'model' => $model,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime' => $mime,
            'schema' => $schema['name'] ?? 'unknown',
            'text_input' => $textContext !== null,
            'large_file' => $largeFileContext !== null,
        ]);

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new GeminiApiException(
                'Unable to read file contents for Gemini.',
                GeminiApiException::CODE_API_ERROR,
                false,
                ['file_path' => $filePath]
            );
        }

        $generationConfig = $this->buildGenerationConfig(0.2, $schema['responseSchema'] ?? null);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $this->fileAnalyzer->buildParts($promptText, $mime, $fileContent, $textContext),
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        $result = $this->sendGeminiRequest($payload, $model, $apiKey);
        $responseBody = $result['body'];
        $textResponse = $result['text'];
        $parsed = $this->responseParser->parseJsonResponse($textResponse);
        $entities = $parsed['entities'] ?? [];
        if (! is_array($entities)) {
            throw new GeminiApiException(
                'Gemini response missing entities array.',
                GeminiApiException::CODE_RESPONSE_INVALID,
                false,
                ['response' => $parsed]
            );
        }

        // Normalize entities to expected format (Gemini sometimes ignores responseSchema)
        $entities = $this->responseParser->normalizeEntities($entities, $schema);

        return [
            'provider' => 'gemini',
            'model' => $model,
            'prompt_used' => $promptText,
            'schema' => $schema,
            'mime' => $mime,
            'file_size' => $fileSize,
            'text_input' => $textContext,
            'large_file' => $largeFileContext,
            'entities' => $entities,
            'raw_response' => [
                'text' => $textResponse,
                'json' => $responseBody,
            ],
        ];
    }

    /**
     * Analyze a file using Gemini Files API (via fileUri).
     */
    public function analyzeFileByUri(
        string $fileUri,
        array $schema,
        string $prompt,
        array $conversationHistory = []
    ): array {
        [$model, $apiKey] = $this->resolveModelAndKey();

        Log::info('[GeminiProvider] Analyzing file by URI', [
            'model' => $model,
            'file_uri' => $fileUri,
            'schema' => $schema['name'] ?? 'unknown',
            'has_conversation_history' => ! empty($conversationHistory),
        ]);

        $generationConfig = $this->buildGenerationConfig(0.2, $schema['responseSchema'] ?? null);

        $contents = $conversationHistory;
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                [
                    'fileData' => [
                        'fileUri' => $fileUri,
                        'mimeType' => 'application/pdf',
                    ],
                ],
            ],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => $generationConfig,
        ];

        Log::debug('[GeminiProvider] Sending request with fileUri', [
            'has_responseJsonSchema' => isset($generationConfig['responseJsonSchema']),
            'conversation_turns' => count($contents),
        ]);

        $result = $this->sendGeminiRequest($payload, $model, $apiKey);

        $parsed = $this->responseParser->parseJsonResponse($result['text']);

        return [
            'provider' => 'gemini',
            'model' => $model,
            'prompt_used' => $prompt,
            'schema' => $schema,
            'data' => $parsed,
            'raw_text' => $result['text'],
            'raw_json' => $result['body'],
        ];
    }

    /**
     * Send a text-only prompt to Gemini (no file attachment).
     */
    public function generateText(string $prompt, ?array $responseSchema = null, float $temperature = 0.1): array
    {
        [$model, $apiKey] = $this->resolveModelAndKey();

        Log::info('[GeminiProvider] Generating text content', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'has_schema' => $responseSchema !== null,
        ]);

        $generationConfig = $this->buildGenerationConfig($temperature, $responseSchema);

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => $generationConfig,
        ];

        $result = $this->sendGeminiRequest($payload, $model, $apiKey);

        return $this->responseParser->parseJsonResponse($result['text']);
    }

    /**
     * Handle request exceptions with proper error categorization.
     *
     * @throws GeminiApiException
     */
    protected function handleRequestException(Exception $e): never
    {
        $message = $e->getMessage();

        $isTimeout = str_contains($message, 'cURL error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'Operation timed out')
            || str_contains($message, 'Connection timed out');

        $isConnectionError = str_contains($message, 'cURL error 7')
            || str_contains($message, 'Could not resolve host')
            || str_contains($message, 'Connection refused');

        $errorCode = match (true) {
            $isTimeout => GeminiApiException::CODE_TIMEOUT,
            $isConnectionError => GeminiApiException::CODE_API_ERROR,
            default => GeminiApiException::CODE_API_ERROR,
        };

        Log::warning('[GeminiProvider] Request failed', [
            'error' => $message,
            'error_code' => $errorCode,
            'is_timeout' => $isTimeout,
            'is_connection_error' => $isConnectionError,
            'will_retry' => true,
        ]);

        throw new GeminiApiException(
            'Gemini request failed: '.$message,
            $errorCode,
            true,
            [
                'error' => $message,
                'is_timeout' => $isTimeout,
                'is_connection_error' => $isConnectionError,
            ]
        );
    }
}
