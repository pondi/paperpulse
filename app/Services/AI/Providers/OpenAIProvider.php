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
        $debugEnabled = config('app.debug');
        $startTime = microtime(true);

        if ($debugEnabled) {
            Log::debug('[OpenAI] Starting receipt analysis', [
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 200).'...',
                'options' => $options,
                'timestamp' => now()->toISOString(),
            ]);
        }

        try {
            $model = $this->modelConfig?->name ?? config('ai.models.receipt', 'gpt-4.1-mini');
            $params = $this->modelConfig?->getOptimalParameters('receipt', $options) ?? [];

            if ($debugEnabled) {
                Log::debug('[OpenAI] Model configuration', [
                    'model' => $model,
                    'model_config' => $this->modelConfig?->toArray(),
                    'optimal_params' => $params,
                ]);
            }

            // Use template service to get structured prompt
            $promptData = $this->promptService->getPrompt('receipt', [
                'content' => $content,
                'merchant_hint' => $options['merchant_hint'] ?? null,
                'extraction_focus' => $options['focus'] ?? null,
                'include_confidence' => $options['include_confidence'] ?? false,
                'debug' => $options['debug'] ?? false,
                'examples' => $options['examples'] ?? [],
            ], array_merge($options, ['model' => $model]));

            if ($debugEnabled) {
                Log::debug('[OpenAI] Prompt data prepared', [
                    'template_name' => $promptData['template_name'] ?? 'unknown',
                    'messages_count' => count($promptData['messages'] ?? []),
                    'messages' => $promptData['messages'] ?? [],
                    'schema_structure' => array_keys($promptData['schema']['properties'] ?? []),
                    'schema_required' => $promptData['schema']['required'] ?? [],
                ]);
            }

            $requestPayload = array_merge([
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
            ], $params);

            if ($debugEnabled) {
                Log::debug('[OpenAI] API request payload', [
                    'model' => $requestPayload['model'],
                    'message_count' => count($requestPayload['messages']),
                    'response_format' => $requestPayload['response_format'],
                    'additional_params' => array_diff_key($requestPayload, [
                        'model' => 1, 'messages' => 1, 'response_format' => 1,
                    ]),
                ]);
            }

            $response = OpenAI::chat()->create($requestPayload);

            if ($debugEnabled) {
                Log::debug('[OpenAI] API response received', [
                    'response_id' => $response->id ?? 'unknown',
                    'model_used' => $response->model ?? $model,
                    'choices_count' => count($response->choices ?? []),
                    'usage' => $response->usage ?? null,
                    'raw_content' => $response->choices[0]->message->content ?? 'no content',
                    'finish_reason' => $response->choices[0]->finishReason ?? 'unknown',
                ]);
            }

            $result = json_decode($response->choices[0]->message->content, true);

            if ($debugEnabled) {
                $jsonError = json_last_error();
                Log::debug('[OpenAI] JSON parsing result', [
                    'json_valid' => $jsonError === JSON_ERROR_NONE,
                    'json_error' => $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                    'parsed_data' => $result,
                    'data_keys' => is_array($result) ? array_keys($result) : 'not array',
                ]);
            }

            // Calculate cost if model config available
            $cost = null;
            if ($this->modelConfig && isset($response->usage)) {
                $cost = $this->modelConfig->estimateCost(
                    $response->usage->promptTokens,
                    $response->usage->completionTokens
                );

                if ($debugEnabled) {
                    Log::debug('[OpenAI] Cost calculation', [
                        'prompt_tokens' => $response->usage->promptTokens,
                        'completion_tokens' => $response->usage->completionTokens,
                        'total_tokens' => $response->usage->totalTokens,
                        'estimated_cost' => $cost,
                    ]);
                }
            }

            $finalResult = [
                'success' => true,
                'data' => $result,
                'provider' => 'openai',
                'model' => $model,
                'template' => $promptData['template_name'],
                'tokens_used' => $response->usage->totalTokens ?? 0,
                'cost_estimate' => $cost,
                'model_config' => $this->modelConfig?->toArray(),
            ];

            if ($debugEnabled) {
                $processingTime = microtime(true) - $startTime;
                Log::debug('[OpenAI] Receipt analysis completed successfully', [
                    'processing_time_ms' => round($processingTime * 1000, 2),
                    'result_summary' => [
                        'success' => $finalResult['success'],
                        'data_keys' => is_array($finalResult['data']) ? array_keys($finalResult['data']) : 'not array',
                        'tokens_used' => $finalResult['tokens_used'],
                        'cost_estimate' => $finalResult['cost_estimate'],
                    ],
                ]);
            }

            return $finalResult;
        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;

            Log::error('OpenAI receipt analysis failed', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'model' => $model ?? 'unknown',
                'content_length' => strlen($content),
                'processing_time_ms' => round($processingTime * 1000, 2),
                'stack_trace' => $debugEnabled ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);

            // If it's a schema validation error, try fallback without strict mode
            if (str_contains($e->getMessage(), 'Invalid schema') || str_contains($e->getMessage(), 'required')) {
                Log::info('[OpenAI] Attempting fallback without strict schema validation', [
                    'original_error' => $e->getMessage(),
                    'fallback_model' => $model ?? 'gpt-4.1-mini',
                ]);

                if ($debugEnabled) {
                    Log::debug('[OpenAI] Fallback request details', [
                        'fallback_model' => $model ?? 'gpt-4.1-mini',
                        'fallback_params' => $params ?? [],
                        'original_messages_count' => count($promptData['messages'] ?? []),
                    ]);
                }

                try {
                    $fallbackPayload = array_merge([
                        'model' => $model ?? 'gpt-4.1-mini',
                        'messages' => $promptData['messages'],
                        'response_format' => ['type' => 'json_object'],
                    ], $params ?? []);

                    $response = OpenAI::chat()->create($fallbackPayload);

                    if ($debugEnabled) {
                        Log::debug('[OpenAI] Fallback response received', [
                            'response_id' => $response->id ?? 'unknown',
                            'raw_content' => $response->choices[0]->message->content ?? 'no content',
                            'usage' => $response->usage ?? null,
                        ]);
                    }

                    $result = json_decode($response->choices[0]->message->content, true);

                    if ($debugEnabled) {
                        Log::debug('[OpenAI] Fallback JSON parsing', [
                            'json_valid' => json_last_error() === JSON_ERROR_NONE,
                            'json_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                            'parsed_data' => $result,
                        ]);
                    }

                    // Normalize the response structure to match validation expectations
                    $normalizedData = $this->normalizeReceiptData($result);

                    $fallbackResult = [
                        'success' => true,
                        'data' => $normalizedData,
                        'provider' => 'openai',
                        'model' => $model ?? 'gpt-4.1-mini',
                        'template' => $promptData['template_name'] ?? 'fallback',
                        'tokens_used' => $response->usage->totalTokens ?? 0,
                        'fallback_used' => true,
                    ];

                    Log::info('[OpenAI] Fallback successful', [
                        'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'tokens_used' => $fallbackResult['tokens_used'],
                    ]);

                    return $fallbackResult;
                } catch (\Exception $fallbackError) {
                    Log::error('[OpenAI] Fallback also failed', [
                        'fallback_error' => $fallbackError->getMessage(),
                        'fallback_error_type' => get_class($fallbackError),
                        'total_processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'fallback_stack_trace' => $debugEnabled ? $fallbackError->getTraceAsString() : 'Enable debug mode for stack trace',
                    ]);
                }
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'openai',
                'model' => $model ?? 'unknown',
                'processing_time_ms' => round($processingTime * 1000, 2),
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
                        'address' => ['type' => ['string', 'null']],
                        'org_number' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']],
                        'website' => ['type' => ['string', 'null']],
                        'category' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => false,
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'quantity' => ['type' => ['number', 'null']],
                            'unit_price' => ['type' => ['number', 'null']],
                            'total_price' => ['type' => 'number'],
                            'category' => ['type' => ['string', 'null']],
                            'vat_rate' => ['type' => ['number', 'null']],
                        ],
                        'required' => ['name', 'total_price'],
                        'additionalProperties' => false,
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'properties' => [
                        'subtotal' => ['type' => ['number', 'null']],
                        'tax_amount' => ['type' => ['number', 'null']],
                        'total_amount' => ['type' => 'number'],
                        'discount_amount' => ['type' => ['number', 'null']],
                        'tip_amount' => ['type' => ['number', 'null']],
                    ],
                    'required' => ['total_amount'],
                    'additionalProperties' => false,
                ],
                'receipt_info' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => ['string', 'null']],
                        'time' => ['type' => ['string', 'null']],
                        'receipt_number' => ['type' => ['string', 'null']],
                        'transaction_id' => ['type' => ['string', 'null']],
                        'cashier' => ['type' => ['string', 'null']],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'payment' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => ['string', 'null']],
                        'card_last_four' => ['type' => ['string', 'null']],
                        'currency' => ['type' => ['string', 'null']],
                        'change_given' => ['type' => ['number', 'null']],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'language' => ['type' => ['string', 'null']],
                        'confidence' => ['type' => ['number', 'null']],
                        'receipt_type' => ['type' => ['string', 'null']],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
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

    /**
     * Normalize receipt data structure from various AI response formats
     * to match the validation expectations
     */
    private function normalizeReceiptData(array $data): array
    {
        $normalized = [];

        // Handle nested receipt structure from fallback response
        if (isset($data['receipt']) && is_array($data['receipt'])) {
            $receiptData = $data['receipt'];

            // Extract items from nested structure
            if (isset($receiptData['items'])) {
                $normalized['items'] = $receiptData['items'];
            }

            // Extract totals from receipt.total
            if (isset($receiptData['total'])) {
                $normalized['totals'] = [
                    'total_amount' => (float) $receiptData['total'],
                    'tax_amount' => $this->extractTaxFromVatData($receiptData['vat'] ?? []),
                ];
            }

            // Extract receipt info from nested structure
            $normalized['receipt_info'] = [
                'date' => $receiptData['date'] ?? date('Y-m-d'),
                'time' => $receiptData['time'] ?? null,
                'receipt_number' => $receiptData['receipt_number'] ?? null,
                'transaction_id' => $receiptData['transaction_id'] ?? null,
            ];

            // Extract payment info
            if (isset($receiptData['payment_method'])) {
                $normalized['payment'] = ['method' => $receiptData['payment_method']];
            }
        }

        // Handle merchant/store mapping (could be at root or nested)
        if (isset($data['merchant'])) {
            $normalized['merchant'] = $data['merchant'];
        } elseif (isset($data['store'])) {
            $normalized['merchant'] = [
                'name' => $data['store']['name'] ?? 'Unknown Merchant',
                'address' => $data['store']['address'] ?? null,
                'org_number' => $data['store']['organization_number'] ?? null,
                'phone' => $data['store']['phone'] ?? null,
            ];
        } elseif (isset($data['vendor'])) {
            $normalized['merchant'] = $data['vendor'];
        }

        // Fallback for direct structure (non-nested)
        if (! isset($normalized['receipt_info']) && (isset($data['receipt_info']) || isset($data['date']))) {
            if (isset($data['receipt_info'])) {
                $normalized['receipt_info'] = $data['receipt_info'];
            } else {
                $normalized['receipt_info'] = [
                    'date' => $data['date'] ?? date('Y-m-d'),
                    'time' => $data['time'] ?? null,
                ];
            }
        }

        // Fallback for totals (non-nested)
        if (! isset($normalized['totals'])) {
            if (isset($data['totals'])) {
                $normalized['totals'] = $data['totals'];
            } elseif (isset($data['total'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total']];
            } elseif (isset($data['total_amount'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total_amount']];
            }
        }

        // Fallback for items (non-nested)
        if (! isset($normalized['items'])) {
            if (isset($data['items'])) {
                $normalized['items'] = $data['items'];
            } elseif (isset($data['line_items'])) {
                $normalized['items'] = $data['line_items'];
            }
        }

        // Fallback for payment (non-nested)
        if (! isset($normalized['payment']) && isset($data['payment'])) {
            $normalized['payment'] = $data['payment'];
        } elseif (! isset($normalized['payment']) && isset($data['payment_method'])) {
            $normalized['payment'] = ['method' => $data['payment_method']];
        }

        // Ensure required structure exists with defaults
        if (! isset($normalized['merchant'])) {
            $normalized['merchant'] = ['name' => 'Unknown Merchant'];
        }

        if (! isset($normalized['totals'])) {
            $normalized['totals'] = ['total_amount' => 0];
        }

        if (! isset($normalized['receipt_info'])) {
            $normalized['receipt_info'] = ['date' => date('Y-m-d')];
        }

        // Ensure items is always an array
        if (! isset($normalized['items'])) {
            $normalized['items'] = [];
        }

        return $normalized;
    }

    /**
     * Extract total tax amount from Norwegian VAT data structure
     */
    private function extractTaxFromVatData(array $vatData): float
    {
        $totalTax = 0.0;

        foreach ($vatData as $vatEntry) {
            if (isset($vatEntry['vat_amount']) && is_numeric($vatEntry['vat_amount'])) {
                $totalTax += (float) $vatEntry['vat_amount'];
            }
        }

        return $totalTax;
    }
}
