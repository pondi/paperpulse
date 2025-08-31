<?php

namespace App\Services\AI\Providers;

use Anthropic;
use Anthropic\Client;
use App\Services\AI\AIService;
use App\Services\AI\ModelConfiguration;
use App\Services\AI\PromptTemplateService;
use App\Services\AI\Shared\AIDataNormalizer;
use App\Services\AI\Shared\AIDebugLogger;
use App\Services\AI\Shared\AIFallbackHandler;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIService
{
    private Client $client;

    private array $defaultOptions;

    protected PromptTemplateService $promptService;

    protected ?ModelConfiguration $modelConfig;

    public function __construct(?ModelConfiguration $modelConfig = null)
    {
        $this->modelConfig = $modelConfig;
        $this->client = Anthropic::factory()
            ->withApiKey(config('services.anthropic.api_key'))
            ->withHttpClient(new \GuzzleHttp\Client([
                'timeout' => config('ai.options.timeout', 120),
            ]))
            ->make();

        $this->defaultOptions = [
            'max_tokens' => 2048,
            'temperature' => 0.1,
        ];

        $this->promptService = app(PromptTemplateService::class);
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        $startTime = microtime(true);

        AIDebugLogger::analysisStart('Anthropic', 'receipt', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200).'...',
            'options' => $options,
        ]);

        try {
            $model = config('ai.models.anthropic_receipt', 'claude-3.7-sonnet');

            AIDebugLogger::modelConfiguration('Anthropic', [
                'model' => $model,
                'model_config' => $this->modelConfig?->toArray(),
                'default_options' => $this->defaultOptions,
            ]);

            // Use template service
            $promptData = $this->promptService->getPrompt('receipt', [
                'content' => $content,
                'merchant_hint' => $options['merchant_hint'] ?? null,
                'extraction_focus' => $options['focus'] ?? null,
            ], array_merge($options, ['provider' => 'anthropic']));

            AIDebugLogger::promptData('Anthropic', $promptData);

            // Extract system and user messages
            $systemMessage = '';
            $userMessage = '';

            foreach ($promptData['messages'] as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } elseif ($message['role'] === 'user') {
                    $userMessage = $message['content'];
                }
            }

            $requestPayload = [
                'model' => $model,
                'max_tokens' => $promptData['options']['max_tokens'] ?? 2048,
                'temperature' => $promptData['options']['temperature'] ?? 0.1,
                'system' => $systemMessage,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'tools' => [
                    [
                        'name' => 'extract_receipt_data',
                        'description' => 'Extract structured data from a Norwegian receipt',
                        'input_schema' => $promptData['schema'],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_receipt_data'],
            ];

            AIDebugLogger::apiRequest('Anthropic', $requestPayload);

            $response = $this->client->messages()->create($requestPayload);

            AIDebugLogger::apiResponse('Anthropic', $response);

            $toolUse = $response->content[0];
            if ($toolUse->type === 'tool_use' && $toolUse->name === 'extract_receipt_data') {
                if ($debugEnabled) {
                    Log::debug('[Anthropic] Tool use response processing', [
                        'tool_name' => $toolUse->name,
                        'tool_id' => $toolUse->id ?? 'unknown',
                        'input_data' => $toolUse->input,
                        'input_keys' => is_object($toolUse->input) || is_array($toolUse->input) ?
                            array_keys((array) $toolUse->input) : 'not array/object',
                    ]);
                }

                $result = $toolUse->input;

                $finalResult = AIFallbackHandler::createSuccessResult('anthropic', $result, [
                    'model' => $model,
                    'template' => $promptData['template_name'],
                    'tokens_used' => $response->usage->inputTokens + $response->usage->outputTokens,
                ]);

                AIDebugLogger::analysisComplete('Anthropic', $finalResult, $startTime);

                return $finalResult;
            }

            if ($debugEnabled) {
                Log::debug('[Anthropic] Unexpected response format', [
                    'content_type' => $toolUse->type ?? 'unknown',
                    'tool_name' => $toolUse->name ?? 'unknown',
                    'expected_name' => 'extract_receipt_data',
                    'full_response' => $response,
                ]);
            }

            throw new \Exception('Unexpected response format from Anthropic API');
        } catch (\Exception $e) {
            AIDebugLogger::analysisError('Anthropic', $e, $startTime, [
                'content_length' => strlen($content),
                'model' => $model ?? 'unknown',
            ]);

            // Try fallback with simpler prompt if appropriate
            if (AIFallbackHandler::shouldAttemptFallback($e)) {
                AIDebugLogger::fallbackAttempt('Anthropic', $e->getMessage(), [
                    'fallback_model' => 'claude-3-haiku-20240307',
                ]);

                try {
                    $fallbackPayload = AIFallbackHandler::createAnthropicFallbackPayload($content);

                    $fallbackResponse = $this->client->messages()->create($fallbackPayload);

                    AIDebugLogger::apiResponse('Anthropic', $fallbackResponse);

                    // Try to parse the response
                    $text = $fallbackResponse->content[0]->text ?? '';
                    $result = json_decode($text, true);

                    if ($debugEnabled) {
                        Log::debug('[Anthropic] Fallback JSON parsing', [
                            'text_length' => strlen($text),
                            'json_valid' => json_last_error() === JSON_ERROR_NONE,
                            'json_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                            'parsed_data' => $result,
                        ]);
                    }

                    if ($result) {
                        $normalizedData = AIDataNormalizer::normalizeReceiptData($result);

                        $fallbackResult = AIFallbackHandler::createSuccessResult('anthropic', $normalizedData, [
                            'model' => 'claude-3-haiku-20240307',
                            'template' => 'fallback',
                            'tokens_used' => $fallbackResponse->usage->inputTokens + $fallbackResponse->usage->outputTokens,
                            'fallback_used' => true,
                        ]);

                        AIDebugLogger::fallbackSuccess('Anthropic', $startTime, $fallbackResult);

                        return $fallbackResult;
                    }
                } catch (\Exception $fallbackError) {
                    AIDebugLogger::analysisError('Anthropic', $fallbackError, $startTime, [
                        'error_context' => 'fallback_failed',
                    ]);
                }
            }

            return AIFallbackHandler::createErrorResult('anthropic', $e, $startTime);
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            $model = config('ai.models.anthropic_document', 'claude-4-opus');

            // Use template service
            $promptData = $this->promptService->getPrompt('document', [
                'content' => substr($content, 0, 8000),
                'domain_context' => $options['domain_context'] ?? null,
                'analysis_depth' => $options['analysis_depth'] ?? 'standard',
                'focus_areas' => $options['focus_areas'] ?? null,
                'summary_length' => $options['summary_length'] ?? '2-3 sentences',
                'max_tags' => $options['max_tags'] ?? '5-8',
                'output_language' => $options['output_language'] ?? null,
                'include_sentiment' => $options['include_sentiment'] ?? false,
            ], array_merge($options, ['provider' => 'anthropic']));

            // Extract system and user messages
            $systemMessage = '';
            $userMessage = '';

            foreach ($promptData['messages'] as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } elseif ($message['role'] === 'user') {
                    $userMessage = $message['content'];
                }
            }

            $response = $this->client->messages()->create([
                'model' => $model,
                'max_tokens' => $promptData['options']['max_tokens'] ?? 3000,
                'temperature' => $promptData['options']['temperature'] ?? 0.2,
                'system' => $systemMessage,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
                'tools' => [
                    [
                        'name' => 'extract_document_data',
                        'description' => 'Extract structured metadata from document',
                        'input_schema' => $promptData['schema'],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_document_data'],
            ]);

            $toolUse = $response->content[0];
            if ($toolUse->type === 'tool_use' && $toolUse->name === 'extract_document_data') {
                $result = $toolUse->input;

                return AIFallbackHandler::createSuccessResult('anthropic', $result, [
                    'model' => $model,
                    'template' => $promptData['template_name'],
                    'tokens_used' => $response->usage->inputTokens + $response->usage->outputTokens,
                ]);
            }

            throw new \Exception('Unexpected response format from Anthropic API');
        } catch (\Exception $e) {
            Log::error('Anthropic document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
                'model' => $model ?? 'unknown',
            ]);

            return AIFallbackHandler::createErrorResult('anthropic', $e, 0);
        }
    }

    public function extractMerchant(string $content): array
    {
        try {
            $promptData = $this->promptService->getPrompt('merchant', [
                'content' => $content,
                'validate_org_number' => true,
                'include_category' => true,
            ], ['provider' => 'anthropic']);

            // Extract system and user messages
            $systemMessage = '';
            $userMessage = '';

            foreach ($promptData['messages'] as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } elseif ($message['role'] === 'user') {
                    $userMessage = $message['content'];
                }
            }

            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => $promptData['options']['max_tokens'] ?? 300,
                'temperature' => $promptData['options']['temperature'] ?? 0.1,
                'system' => $systemMessage,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
                'tools' => [
                    [
                        'name' => 'extract_merchant',
                        'description' => 'Extract merchant information',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'address' => ['type' => 'string'],
                                'org_number' => ['type' => 'string'],
                                'phone' => ['type' => 'string'],
                            ],
                            'required' => ['name'],
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_merchant'],
            ]);

            $toolUse = $response->content[0];

            return $toolUse->type === 'tool_use' ? $toolUse->input : [];

        } catch (\Exception $e) {
            Log::error('Merchant extraction failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        try {
            $promptData = $this->promptService->getPrompt('summary', [
                'content' => substr($content, 0, 6000),
                'max_length' => $maxLength,
            ], ['provider' => 'anthropic']);

            // Extract system and user messages
            $systemMessage = '';
            $userMessage = '';

            foreach ($promptData['messages'] as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } elseif ($message['role'] === 'user') {
                    $userMessage = $message['content'];
                }
            }

            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => $promptData['options']['max_tokens'] ?? (int) ($maxLength / 2),
                'temperature' => $promptData['options']['temperature'] ?? 0.3,
                'system' => $systemMessage,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
            ]);

            return trim($response->content[0]->text);
        } catch (\Exception $e) {
            Log::error('Summary generation failed', ['error' => $e->getMessage()]);

            return 'Summary generation failed';
        }
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => 200,
                'temperature' => 0.2,
                'system' => "Extract up to {$maxTags} relevant tags from document content. Focus on meaningful, specific keywords.",
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 4000),
                    ],
                ],
                'tools' => [
                    [
                        'name' => 'extract_tags',
                        'description' => 'Extract relevant tags',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'tags' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'maxItems' => $maxTags,
                                ],
                            ],
                            'required' => ['tags'],
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_tags'],
            ]);

            $toolUse = $response->content[0];

            return $toolUse->type === 'tool_use' ? ($toolUse->input['tags'] ?? []) : [];

        } catch (\Exception $e) {
            Log::error('Tag suggestion failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function classifyDocumentType(string $content): string
    {
        try {
            $types = [
                'invoice', 'contract', 'report', 'letter', 'memo',
                'presentation', 'spreadsheet', 'email', 'legal',
                'financial', 'technical', 'other',
            ];

            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => 50,
                'temperature' => 0.1,
                'system' => 'Classify document type. Return only one of: '.implode(', ', $types),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 2000),
                    ],
                ],
            ]);

            $type = strtolower(trim($response->content[0]->text));

            return in_array($type, $types) ? $type : 'other';
        } catch (\Exception $e) {
            Log::error('Document classification failed', ['error' => $e->getMessage()]);

            return 'other';
        }
    }

    public function extractEntities(string $content, array $types = []): array
    {
        try {
            $defaultTypes = ['people', 'organizations', 'locations', 'dates', 'amounts'];
            $types = empty($types) ? $defaultTypes : array_intersect($types, $defaultTypes);

            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => 500,
                'temperature' => 0.1,
                'system' => 'Extract entities from text with high accuracy.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Extract these entity types: '.implode(', ', $types)."\n\nText:\n".substr($content, 0, 4000),
                    ],
                ],
                'tools' => [
                    [
                        'name' => 'extract_entities',
                        'description' => 'Extract specified entity types',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => array_fill_keys($types, [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ]),
                            'required' => $types,
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_entities'],
            ]);

            $toolUse = $response->content[0];

            return $toolUse->type === 'tool_use' ? $toolUse->input : array_fill_keys($types, []);

        } catch (\Exception $e) {
            Log::error('Entity extraction failed', ['error' => $e->getMessage()]);

            return array_fill_keys($types, []);
        }
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }
}
