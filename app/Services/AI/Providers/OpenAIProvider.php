<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIService;
use App\Services\AI\PromptTemplateService;
use App\Services\AI\Shared\AIDataNormalizer;
use App\Services\AI\Shared\AIDebugLogger;
use App\Services\AI\Shared\AIFallbackHandler;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIService
{
    protected PromptTemplateService $promptService;

    protected ?array $modelConfig;

    public function __construct(?array $modelConfig = null)
    {
        $this->modelConfig = $modelConfig; // unused in simplified core
        $this->promptService = app(PromptTemplateService::class);
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        $startTime = microtime(true);

        AIDebugLogger::analysisStart('OpenAI', 'receipt', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200).'...',
            'options' => $options,
        ]);

        try {
            $model = config('ai.models.receipt', 'gpt-4o-mini');
            $params = [
                'max_tokens' => config('ai.options.max_tokens.receipt', 1024),
                'temperature' => config('ai.options.temperature.receipt', 0.1),
            ];

            AIDebugLogger::modelConfiguration('OpenAI', [
                'model' => $model,
                'model_config' => $this->modelConfig?->toArray(),
                'optimal_params' => $params,
            ]);

            // Use template service to get structured prompt
            $promptData = $this->promptService->getPrompt('receipt', [
                'content' => $content,
                'merchant_hint' => $options['merchant_hint'] ?? null,
                'extraction_focus' => $options['focus'] ?? null,
                'include_confidence' => $options['include_confidence'] ?? false,
                'debug' => $options['debug'] ?? false,
                'examples' => $options['examples'] ?? [],
            ], array_merge($options, ['model' => $model]));

            AIDebugLogger::promptData('OpenAI', $promptData);

            $requestPayload = array_merge([
                'model' => $model,
                'messages' => $promptData['messages'],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'receipt_analysis',
                        'description' => 'Structured receipt data extraction',
                        'schema' => $promptData['schema'],
                        // Make strict mode configurable; default to false for production reliability
                        'strict' => (bool) config('ai.options.strict_json_schema', false),
                    ],
                ],
            ], $params);

            AIDebugLogger::apiRequest('OpenAI', $requestPayload);

            $response = OpenAI::chat()->create($requestPayload);

            AIDebugLogger::apiResponse('OpenAI', $response);

            $result = json_decode($response->choices[0]->message->content, true);

            $cost = null;

            $finalResult = AIFallbackHandler::createSuccessResult('openai', $result, [
                'model' => $model,
                'template' => $promptData['template_name'],
                'tokens_used' => $response->usage->totalTokens ?? 0,
                'cost_estimate' => $cost,
                'model_config' => null,
            ]);

            AIDebugLogger::analysisComplete('OpenAI', $finalResult, $startTime);

            return $finalResult;
        } catch (\Exception $e) {
            AIDebugLogger::analysisError('OpenAI', $e, $startTime, [
                'model' => $model ?? 'unknown',
                'content_length' => strlen($content),
            ]);

            // If it's a schema validation error, try fallback without strict mode
            if (AIFallbackHandler::shouldAttemptFallback($e)) {
                AIDebugLogger::fallbackAttempt('OpenAI', $e->getMessage(), [
                    'fallback_model' => $model ?? 'gpt-4.1-mini',
                ]);

                try {
                    $fallbackPayload = AIFallbackHandler::createOpenAIFallbackPayload(
                        $promptData['messages'],
                        $model
                    );
                    // Merge additional params if available
                    $fallbackPayload = array_merge($fallbackPayload, $params);

                    $response = OpenAI::chat()->create($fallbackPayload);

                    AIDebugLogger::apiResponse('OpenAI', $response);

                    $result = json_decode($response->choices[0]->message->content, true);

                    // Normalize the response structure to match validation expectations
                    $normalizedData = AIDataNormalizer::normalizeReceiptData($result);

                    $fallbackResult = AIFallbackHandler::createSuccessResult('openai', $normalizedData, [
                        'model' => $model ?? 'gpt-4.1-mini',
                        'template' => $promptData['template_name'] ?? 'fallback',
                        'tokens_used' => $response->usage->totalTokens ?? 0,
                        'fallback_used' => true,
                    ]);

                    AIDebugLogger::fallbackSuccess('OpenAI', $startTime, $fallbackResult);

                    return $fallbackResult;
                } catch (\Exception $fallbackError) {
                    AIDebugLogger::analysisError('OpenAI', $fallbackError, $startTime, [
                        'error_context' => 'fallback_failed',
                    ]);
                }
            }

            return AIFallbackHandler::createErrorResult('openai', $e, $startTime, [
                'model' => $model ?? 'unknown',
            ]);
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            $model = config('ai.models.document', 'gpt-4o');

            // Use template service to get structured prompt
            $promptData = $this->promptService->getPrompt('document', [
                'content' => substr($content, 0, 8000),
                'domain_context' => $options['domain_context'] ?? null,
                'analysis_depth' => $options['analysis_depth'] ?? 'standard',
                'focus_areas' => $options['focus_areas'] ?? null,
                'summary_length' => $options['summary_length'] ?? '2-3 sentences',
                'max_tags' => $options['max_tags'] ?? '5-8',
                'output_language' => $options['output_language'] ?? null,
                'include_sentiment' => $options['include_sentiment'] ?? false,
            ], $options);

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $promptData['messages'],
                'temperature' => $promptData['options']['temperature'] ?? 0.2,
                'max_tokens' => $promptData['options']['max_tokens'] ?? 3000,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'document_analysis',
                        'description' => 'Structured document metadata extraction',
                        'schema' => $promptData['schema'],
                        'strict' => true,
                    ],
                ],
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return AIFallbackHandler::createSuccessResult('openai', $result, [
                'model' => $model,
                'template' => $promptData['template_name'],
                'tokens_used' => $response->usage->totalTokens ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('OpenAI document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);

            return AIFallbackHandler::createErrorResult('openai', $e, 0);
        }
    }

    public function extractMerchant(string $content): array
    {
        try {
            $promptData = $this->promptService->getPrompt('merchant', [
                'content' => $content,
                'validate_org_number' => true,
                'include_category' => true,
            ]);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4.1-mini',
                'messages' => $promptData['messages'],
                'max_tokens' => $promptData['options']['max_tokens'] ?? 200,
                'temperature' => $promptData['options']['temperature'] ?? 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return $result ?? [];
        } catch (\Exception $e) {
            Log::error('Merchant extraction failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        try {
            $promptData = $this->promptService->getPrompt('summary', [
                'content' => substr($content, 0, 3000),
                'max_length' => $maxLength,
            ]);

            $response = OpenAI::chat()->create([
                'model' => config('ai.models.summary', 'gpt-4o-mini'),
                'messages' => $promptData['messages'],
                'temperature' => $promptData['options']['temperature'] ?? 0.3,
                'max_tokens' => $promptData['options']['max_tokens'] ?? (int) ($maxLength / 4),
            ]);

            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Log::error('Summary generation failed', ['error' => $e->getMessage()]);

            return 'Summary generation failed';
        }
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        try {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => $maxTags,
                    ],
                ],
                'required' => ['tags'],
            ];

            $response = OpenAI::chat()->create([
                'model' => config('ai.models.entities', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Extract up to {$maxTags} relevant tags from the document content. Tags should be concise, relevant keywords or phrases.",
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 4000),
                    ],
                ],
                'temperature' => 0.2,
                'max_tokens' => 150,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'tag_extraction',
                        'schema' => $schema,
                        'strict' => true,
                    ],
                ],
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return $result['tags'] ?? [];
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

            $response = OpenAI::chat()->create([
                'model' => config('ai.models.classification', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Classify document type. Return one of: '.implode(', ', $types),
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 1500),
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 10,
            ]);

            $type = strtolower(trim($response->choices[0]->message->content));

            return in_array($type, $types) ? $type : 'other';
        } catch (\Exception $e) {
            Log::error('Document classification failed', ['error' => $e->getMessage()]);

            return 'other';
        }
    }

    public function extractEntities(string $content, array $types = []): array
    {
        $defaultTypes = ['people', 'organizations', 'locations', 'dates', 'amounts'];
        $types = empty($types) ? $defaultTypes : array_intersect($types, $defaultTypes);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extract entities from text. Return JSON with keys: '.implode(', ', $types),
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 2000),
                    ],
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return array_intersect_key($result, array_flip($types));
        } catch (\Exception $e) {
            Log::error('Entity extraction failed', ['error' => $e->getMessage()]);

            return array_fill_keys($types, []);
        }
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
