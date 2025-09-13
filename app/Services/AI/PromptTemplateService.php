<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Services\AI\Prompt\TemplatePathResolver;
use App\Services\AI\Prompt\PromptContentParser;
use App\Services\AI\Prompt\FallbackPromptProvider;
use App\Services\AI\Prompt\TemplateOptionsProvider;
use Illuminate\Support\Str;

class PromptTemplateService
{
    protected array $defaultTemplates = [
        'receipt' => 'ai.prompts.receipt',
        'document' => 'ai.prompts.document',
        'merchant' => 'ai.prompts.merchant',
        'line_items' => 'ai.prompts.line_items',
        'summary' => 'ai.prompts.summary',
        'classification' => 'ai.prompts.classification',
        'tags' => 'ai.prompts.tags',
        'entities' => 'ai.prompts.entities',
    ];

    protected array $compiledTemplates = [];

    /**
     * Get compiled prompt for a template
     */
    public function getPrompt(string $templateName, array $data = [], array $options = []): array
    {
        try {
            $templatePath = TemplatePathResolver::resolve($templateName, $this->defaultTemplates);

            if (! View::exists($templatePath)) {
                throw new Exception("Template '{$templatePath}' not found");
            }

            // Add system-level data
            $templateData = array_merge([
                'timestamp' => now()->toISOString(),
                'template_name' => $templateName,
                'language' => $options['language'] ?? 'no',
                'model' => $options['model'] ?? 'gpt-4.1-mini',
                'provider' => $options['provider'] ?? 'openai',
            ], $data);

            // Render the template
            $renderedContent = View::make($templatePath, $templateData)->render();

            // Parse the rendered content into system and user messages
            return PromptContentParser::parse(
                $renderedContent,
                fn(string $name, array $opts) => $this->getSchema($name, $opts),
                fn(string $name, array $opts) => $this->getTemplateOptions($name, $opts),
                $templateName,
                $options
            );

        } catch (Exception $e) {
            Log::error('[PromptTemplateService] Template rendering failed', [
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            // Return fallback prompt
            return [
                'messages' => [
                    ['role' => 'user', 'content' => FallbackPromptProvider::forTemplate($templateName)],
                ],
                'template_name' => $templateName,
                'schema' => $this->getSchema($templateName, $options),
                'options' => $this->getTemplateOptions($templateName, $options),
            ];
        }
    }

    /**
     * Get schema for a template
     */
    public function getSchema(string $templateName, array $options = []): array
    {
        $schemaMethod = 'get'.Str::studly($templateName).'Schema';

        if (method_exists($this, $schemaMethod)) {
            return $this->{$schemaMethod}($options);
        }

        return $this->getDefaultSchema($templateName);
    }

    /**
     * Register a custom template
     */
    public function registerTemplate(string $name, string $viewPath): void
    {
        $this->defaultTemplates[$name] = $viewPath;

        // Clear compiled cache for this template
        if (isset($this->compiledTemplates[$name])) {
            unset($this->compiledTemplates[$name]);
        }
    }

    /**
     * Get all available templates
     */
    public function getAvailableTemplates(): array
    {
        return array_keys($this->defaultTemplates);
    }

    /**
     * Get template path, checking for custom overrides
     */
    // Path resolution moved to TemplatePathResolver

    /**
     * Parse rendered prompt content into structured format
     */
    // Parsing moved to PromptContentParser

    /**
     * Get fallback prompt when template fails
     */
    // Fallback moved to FallbackPromptProvider

    /**
     * Get template-specific options
     */
    protected function getTemplateOptions(string $templateName, array $userOptions = []): array
    {
        return TemplateOptionsProvider::forTemplate($templateName, $userOptions);
    }

    // Schema methods for different templates
    protected function getReceiptSchema(array $options = []): array
    {
        $strictMode = $options['strict_mode'] ?? true;

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
                        'vat_number' => [
                            'type' => 'string',
                            'description' => 'Norwegian organization/VAT number (9 digits)',
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
                    'additionalProperties' => false,
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
                            'vendor' => [
                                'type' => 'string',
                                'description' => 'Product vendor/brand if identifiable (e.g., Philips, Siemens)',
                            ],
                            'brand' => [
                                'type' => 'string',
                                'description' => 'Alias of vendor if provider uses brand terminology',
                            ],
                        ],
                        'required' => ['name', 'total_price'],
                        'additionalProperties' => false,
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
                    'additionalProperties' => false,
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
                    'required' => [],
                    'additionalProperties' => false,
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
                    'required' => [],
                    'additionalProperties' => false,
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
                    'required' => [],
                    'additionalProperties' => false,
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
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['merchant', 'totals', 'receipt_info'],
            'additionalProperties' => false,
        ];
    }

    protected function getDocumentSchema(array $options = []): array
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
                'vendors' => [
                    'type' => 'array',
                    'description' => 'Distinct list of identified product vendors/brands present on this receipt',
                    'items' => [
                        'type' => 'string'
                    ],
                ],
            ],
            // Keep top-level required minimal and relevant to receipts
            'required' => ['merchant', 'totals', 'receipt_info'],
            'additionalProperties' => false,
        ];
    }

    protected function getDefaultSchema(string $templateName): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
            ],
            'required' => ['result'],
            'additionalProperties' => false,
        ];
    }
}
