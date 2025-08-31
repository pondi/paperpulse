<?php

namespace App\Services\AI\Providers;

use Anthropic;
use Anthropic\Client;
use App\Services\AI\AIService;
use App\Services\AI\ModelConfiguration;
use App\Services\AI\PromptTemplateService;
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
        try {
            $model = config('ai.models.anthropic_receipt', 'claude-3.7-sonnet');

            // Use template service
            $promptData = $this->promptService->getPrompt('receipt', [
                'content' => $content,
                'merchant_hint' => $options['merchant_hint'] ?? null,
                'extraction_focus' => $options['focus'] ?? null,
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
            ]);

            $toolUse = $response->content[0];
            if ($toolUse->type === 'tool_use' && $toolUse->name === 'extract_receipt_data') {
                $result = $toolUse->input;

                return [
                    'success' => true,
                    'data' => $result,
                    'provider' => 'anthropic',
                    'model' => $model,
                    'template' => $promptData['template_name'],
                    'tokens_used' => $response->usage->inputTokens + $response->usage->outputTokens,
                ];
            }

            throw new \Exception('Unexpected response format from Anthropic API');
        } catch (\Exception $e) {
            Log::error('Anthropic receipt analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
                'model' => $model ?? 'unknown',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'anthropic',
            ];
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

                return [
                    'success' => true,
                    'data' => $result,
                    'provider' => 'anthropic',
                    'model' => $model,
                    'template' => $promptData['template_name'],
                    'tokens_used' => $response->usage->inputTokens + $response->usage->outputTokens,
                ];
            }

            throw new \Exception('Unexpected response format from Anthropic API');
        } catch (\Exception $e) {
            Log::error('Anthropic document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
                'model' => $model ?? 'unknown',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'anthropic',
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

    public function extractLineItems(string $content): array
    {
        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-3.7-sonnet',
                'max_tokens' => 800,
                'temperature' => 0.1,
                'system' => 'Extract line items from receipt text with high accuracy.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Extract line items from this receipt:\n\n{$content}",
                    ],
                ],
                'tools' => [
                    [
                        'name' => 'extract_line_items',
                        'description' => 'Extract line items from receipt',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'quantity' => ['type' => 'number'],
                                            'unit_price' => ['type' => 'number'],
                                            'total_price' => ['type' => 'number'],
                                        ],
                                        'required' => ['name', 'total_price'],
                                    ],
                                ],
                            ],
                            'required' => ['items'],
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'extract_line_items'],
            ]);

            $toolUse = $response->content[0];

            return $toolUse->type === 'tool_use' ? ($toolUse->input['items'] ?? []) : [];

        } catch (\Exception $e) {
            Log::error('Line items extraction failed', ['error' => $e->getMessage()]);

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

    private function getReceiptAnalysisSystemPrompt(): string
    {
        return 'Du er en ekspert på å analysere kvitteringer fra Norge. Din oppgave er å trekke ut strukturert informasjon fra kvitteringstekst med høy nøyaktighet.

Fokusområder:
- Identifiser butikk/merchant informasjon nøyaktig
- Trekk ut alle varer med riktige priser og mengder
- Beregn totaler, MVA og rabatter korrekt
- Identifiser betalingsmetode og transaksjonsinformasjon
- Håndter norske formater for dato, tid og valuta

Returner alltid strukturerte data ved hjelp av extract_receipt_data funksjonen.';
    }

    private function getReceiptAnalysisUserPrompt(string $content): string
    {
        return "Analyser denne norske kvitteringen og trekk ut all relevant strukturert informasjon:

<receipt_content>
{$content}
</receipt_content>

Vær spesielt oppmerksom på:
- Norske formater for dato (dd.mm.åååå eller dd/mm/åååå)
- Norske valutaformater (kr, NOK, komma som desimalseparator)
- MVA-satser som brukes i Norge (25%, 15%, 12%, 0%)
- Norske organisasjonsnummer (9 siffer)";
    }

    private function getReceiptSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'merchant' => [
                    'type' => 'object',
                    'description' => 'Merchant/store information',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the store or business',
                        ],
                        'address' => [
                            'type' => 'string',
                            'description' => 'Physical address of the store',
                        ],
                        'org_number' => [
                            'type' => 'string',
                            'description' => 'Norwegian organization number (9 digits)',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Phone number',
                        ],
                        'website' => [
                            'type' => 'string',
                            'description' => 'Website URL if present',
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Email address if present',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Business category (grocery, restaurant, retail, etc.)',
                        ],
                    ],
                    'required' => ['name'],
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'List of purchased items',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Item name or description',
                            ],
                            'quantity' => [
                                'type' => 'number',
                                'description' => 'Quantity purchased',
                            ],
                            'unit_price' => [
                                'type' => 'number',
                                'description' => 'Price per unit',
                            ],
                            'total_price' => [
                                'type' => 'number',
                                'description' => 'Total price for this item',
                            ],
                            'discount_amount' => [
                                'type' => 'number',
                                'description' => 'Discount amount if applicable',
                            ],
                            'vat_rate' => [
                                'type' => 'number',
                                'description' => 'VAT rate (0.25 for 25%, etc.)',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'Item category',
                            ],
                            'sku' => [
                                'type' => 'string',
                                'description' => 'Product code or SKU if present',
                            ],
                        ],
                        'required' => ['name', 'total_price'],
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'description' => 'Receipt totals and taxes',
                    'properties' => [
                        'subtotal' => [
                            'type' => 'number',
                            'description' => 'Subtotal before tax and discounts',
                        ],
                        'total_discount' => [
                            'type' => 'number',
                            'description' => 'Total discount amount',
                        ],
                        'tax_amount' => [
                            'type' => 'number',
                            'description' => 'Total VAT/tax amount',
                        ],
                        'total_amount' => [
                            'type' => 'number',
                            'description' => 'Final total amount paid',
                        ],
                        'tip_amount' => [
                            'type' => 'number',
                            'description' => 'Tip amount if applicable',
                        ],
                    ],
                    'required' => ['total_amount'],
                ],
                'receipt_info' => [
                    'type' => 'object',
                    'description' => 'Receipt metadata',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Receipt date in YYYY-MM-DD format',
                        ],
                        'time' => [
                            'type' => 'string',
                            'description' => 'Receipt time in HH:MM format',
                        ],
                        'receipt_number' => [
                            'type' => 'string',
                            'description' => 'Receipt or invoice number',
                        ],
                        'transaction_id' => [
                            'type' => 'string',
                            'description' => 'Transaction ID',
                        ],
                        'cashier' => [
                            'type' => 'string',
                            'description' => 'Cashier name or ID',
                        ],
                        'terminal_id' => [
                            'type' => 'string',
                            'description' => 'Terminal or register ID',
                        ],
                    ],
                ],
                'payment' => [
                    'type' => 'object',
                    'description' => 'Payment information',
                    'properties' => [
                        'method' => [
                            'type' => 'string',
                            'description' => 'Payment method (cash, card, mobile, etc.)',
                        ],
                        'card_type' => [
                            'type' => 'string',
                            'description' => 'Card type if applicable (Visa, MasterCard, etc.)',
                        ],
                        'card_last_four' => [
                            'type' => 'string',
                            'description' => 'Last four digits of card',
                        ],
                        'currency' => [
                            'type' => 'string',
                            'description' => 'Currency code (NOK, EUR, etc.)',
                        ],
                        'change_given' => [
                            'type' => 'number',
                            'description' => 'Change given if cash payment',
                        ],
                        'amount_paid' => [
                            'type' => 'number',
                            'description' => 'Amount paid by customer',
                        ],
                    ],
                ],
                'loyalty_program' => [
                    'type' => 'object',
                    'description' => 'Loyalty program information',
                    'properties' => [
                        'program_name' => [
                            'type' => 'string',
                            'description' => 'Name of loyalty program',
                        ],
                        'member_id' => [
                            'type' => 'string',
                            'description' => 'Member ID or number',
                        ],
                        'points_earned' => [
                            'type' => 'number',
                            'description' => 'Points earned from this purchase',
                        ],
                        'points_used' => [
                            'type' => 'number',
                            'description' => 'Points used for discounts',
                        ],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Additional metadata',
                    'properties' => [
                        'language' => [
                            'type' => 'string',
                            'description' => 'Receipt language (no, en, etc.)',
                        ],
                        'confidence_score' => [
                            'type' => 'number',
                            'description' => 'Confidence score for extraction (0-1)',
                        ],
                        'receipt_type' => [
                            'type' => 'string',
                            'description' => 'Type of receipt (sale, return, void, etc.)',
                        ],
                        'processing_notes' => [
                            'type' => 'string',
                            'description' => 'Any notes about processing challenges',
                        ],
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Document title or main heading',
                ],
                'document_type' => [
                    'type' => 'string',
                    'enum' => ['invoice', 'contract', 'report', 'letter', 'memo', 'presentation', 'spreadsheet', 'email', 'legal', 'financial', 'technical', 'other'],
                    'description' => 'Classification of document type',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'Brief summary of document content',
                ],
                'entities' => [
                    'type' => 'object',
                    'properties' => [
                        'people' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of people mentioned',
                        ],
                        'organizations' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Organizations mentioned',
                        ],
                        'locations' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Locations mentioned',
                        ],
                        'dates' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Important dates found',
                        ],
                        'amounts' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Financial amounts mentioned',
                        ],
                        'phone_numbers' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Phone numbers found',
                        ],
                        'emails' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Email addresses found',
                        ],
                        'references' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Reference numbers, IDs, etc.',
                        ],
                    ],
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Relevant tags for categorization',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Primary language of document',
                ],
                'key_phrases' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Important phrases or terms',
                ],
                'sentiment' => [
                    'type' => 'object',
                    'properties' => [
                        'overall' => [
                            'type' => 'string',
                            'enum' => ['positive', 'negative', 'neutral'],
                            'description' => 'Overall sentiment',
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Confidence in sentiment analysis (0-1)',
                        ],
                    ],
                ],
                'urgency' => [
                    'type' => 'object',
                    'properties' => [
                        'level' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high', 'critical'],
                            'description' => 'Urgency level of document',
                        ],
                        'indicators' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Phrases indicating urgency',
                        ],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'page_count' => [
                            'type' => 'number',
                            'description' => 'Estimated page count',
                        ],
                        'word_count' => [
                            'type' => 'number',
                            'description' => 'Estimated word count',
                        ],
                        'confidence_score' => [
                            'type' => 'number',
                            'description' => 'Overall extraction confidence (0-1)',
                        ],
                        'processing_notes' => [
                            'type' => 'string',
                            'description' => 'Notes about processing challenges or findings',
                        ],
                    ],
                ],
            ],
            'required' => ['title', 'document_type', 'summary', 'language'],
        ];
    }
}
