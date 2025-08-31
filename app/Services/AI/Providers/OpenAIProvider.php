<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIService;
use App\Services\AI\ModelConfiguration;
use App\Services\AI\PromptTemplateService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIService
{
    protected PromptTemplateService $promptService;

    protected ?ModelConfiguration $modelConfig;

    public function __construct(?ModelConfiguration $modelConfig = null)
    {
        $this->modelConfig = $modelConfig;
        $this->promptService = app(PromptTemplateService::class);
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        try {
            $model = $this->modelConfig?->name ?? config('ai.models.receipt', 'gpt-4.1-mini');
            $params = $this->modelConfig?->getOptimalParameters('receipt', $options) ?? [];

            // Use template service to get structured prompt
            $promptData = $this->promptService->getPrompt('receipt', [
                'content' => $content,
                'merchant_hint' => $options['merchant_hint'] ?? null,
                'extraction_focus' => $options['focus'] ?? null,
                'include_confidence' => $options['include_confidence'] ?? false,
                'debug' => $options['debug'] ?? false,
                'examples' => $options['examples'] ?? [],
            ], array_merge($options, ['model' => $model]));

            $response = OpenAI::chat()->create(array_merge([
                'model' => $model,
                'messages' => $promptData['messages'],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'receipt_analysis',
                        'description' => 'Structured receipt data extraction',
                        'schema' => $promptData['schema'],
                        'strict' => true,
                    ],
                ],
            ], $params));

            $result = json_decode($response->choices[0]->message->content, true);

            // Calculate cost if model config available
            $cost = null;
            if ($this->modelConfig && isset($response->usage)) {
                $cost = $this->modelConfig->estimateCost(
                    $response->usage->promptTokens,
                    $response->usage->completionTokens
                );
            }

            return [
                'success' => true,
                'data' => $result,
                'provider' => 'openai',
                'model' => $model,
                'template' => $promptData['template_name'],
                'tokens_used' => $response->usage->totalTokens ?? 0,
                'cost_estimate' => $cost,
                'model_config' => $this->modelConfig?->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI receipt analysis failed', [
                'error' => $e->getMessage(),
                'model' => $model ?? 'unknown',
                'content_length' => strlen($content),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'openai',
                'model' => $model ?? 'unknown',
            ];
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            $model = config('ai.models.document', 'gpt-4.1');

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

            return [
                'success' => true,
                'data' => $result,
                'provider' => 'openai',
                'model' => $model,
                'template' => $promptData['template_name'],
                'tokens_used' => $response->usage->totalTokens ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'openai',
            ];
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

    public function extractLineItems(string $content): array
    {
        try {
            $response = OpenAI::completions()->create([
                'model' => 'gpt-3.5-turbo-instruct',
                'prompt' => "Extract line items from this receipt. Return JSON array:\n\n".$content,
                'max_tokens' => 500,
                'temperature' => 0.1,
            ]);

            $result = json_decode(trim($response->choices[0]->text), true);

            return $result ?? [];
        } catch (\Exception $e) {
            Log::error('Line items extraction failed', ['error' => $e->getMessage()]);

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
                'model' => 'gpt-3.5-turbo',
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
                'model' => 'gpt-4.1-mini',
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
                'model' => 'gpt-3.5-turbo',
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

    private function getReceiptSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'merchant' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'org_number' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'website' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                            'unit_price' => ['type' => 'number'],
                            'total_price' => ['type' => 'number'],
                            'category' => ['type' => 'string'],
                            'vat_rate' => ['type' => 'number'],
                        ],
                        'required' => ['name', 'quantity', 'total_price'],
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'properties' => [
                        'subtotal' => ['type' => 'number'],
                        'tax_amount' => ['type' => 'number'],
                        'total_amount' => ['type' => 'number'],
                        'discount_amount' => ['type' => 'number'],
                        'tip_amount' => ['type' => 'number'],
                    ],
                    'required' => ['total_amount'],
                ],
                'receipt_info' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string'],
                        'time' => ['type' => 'string'],
                        'receipt_number' => ['type' => 'string'],
                        'transaction_id' => ['type' => 'string'],
                        'cashier' => ['type' => 'string'],
                    ],
                ],
                'payment' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string'],
                        'card_last_four' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                        'change_given' => ['type' => 'number'],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'language' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'receipt_type' => ['type' => 'string'],
                    ],
                ],
            ],
            'required' => ['merchant', 'totals', 'receipt_info'],
        ];
    }

    private function getDocumentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'document_type' => [
                    'type' => 'string',
                    'enum' => ['invoice', 'contract', 'report', 'letter', 'memo', 'presentation', 'spreadsheet', 'email', 'legal', 'financial', 'technical', 'other'],
                ],
                'summary' => ['type' => 'string'],
                'entities' => [
                    'type' => 'object',
                    'properties' => [
                        'people' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'organizations' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'locations' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'dates' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'amounts' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'phone_numbers' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'emails' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'language' => ['type' => 'string'],
                'key_phrases' => ['type' => 'array', 'items' => ['type' => 'string']],
                'sentiment' => [
                    'type' => 'object',
                    'properties' => [
                        'overall' => ['type' => 'string', 'enum' => ['positive', 'negative', 'neutral']],
                        'confidence' => ['type' => 'number'],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'page_count' => ['type' => 'number'],
                        'word_count' => ['type' => 'number'],
                        'confidence' => ['type' => 'number'],
                        'processing_notes' => ['type' => 'string'],
                    ],
                ],
            ],
            'required' => ['title', 'document_type', 'summary', 'language'],
        ];
    }
}
