<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
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
            $templatePath = $this->getTemplatePath($templateName);

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
            return $this->parsePromptContent($renderedContent, $templateName, $options);

        } catch (Exception $e) {
            Log::error('[PromptTemplateService] Template rendering failed', [
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            // Return fallback prompt
            return $this->getFallbackPrompt($templateName, $data, $options);
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
    protected function getTemplatePath(string $templateName): string
    {
        // Check for custom template first
        $customPath = "ai.prompts.custom.{$templateName}";
        if (View::exists($customPath)) {
            return $customPath;
        }

        // Use default template
        return $this->defaultTemplates[$templateName] ?? "ai.prompts.{$templateName}";
    }

    /**
     * Parse rendered prompt content into structured format
     */
    protected function parsePromptContent(string $content, string $templateName, array $options): array
    {
        // Split content by sections using XML-like tags
        $sections = [];

        // Extract system prompt
        if (preg_match('/<system>(.*?)<\/system>/s', $content, $matches)) {
            $sections['system'] = trim($matches[1]);
        }

        // Extract user prompt
        if (preg_match('/<user>(.*?)<\/user>/s', $content, $matches)) {
            $sections['user'] = trim($matches[1]);
        }

        // Extract assistant prompt (for few-shot examples)
        if (preg_match('/<assistant>(.*?)<\/assistant>/s', $content, $matches)) {
            $sections['assistant'] = trim($matches[1]);
        }

        // If no sections found, treat entire content as user prompt
        if (empty($sections)) {
            $sections['user'] = trim($content);
        }

        // Build messages array
        $messages = [];

        if (! empty($sections['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $sections['system'],
            ];
        }

        if (! empty($sections['user'])) {
            $messages[] = [
                'role' => 'user',
                'content' => $sections['user'],
            ];
        }

        if (! empty($sections['assistant'])) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $sections['assistant'],
            ];
        }

        return [
            'messages' => $messages,
            'template_name' => $templateName,
            'schema' => $this->getSchema($templateName, $options),
            'options' => $this->getTemplateOptions($templateName, $options),
        ];
    }

    /**
     * Get fallback prompt when template fails
     */
    protected function getFallbackPrompt(string $templateName, array $data, array $options): array
    {
        $fallbackPrompts = [
            'receipt' => 'Analyze this Norwegian receipt and extract structured data in JSON format.',
            'document' => 'Analyze this document and extract structured metadata in JSON format.',
            'merchant' => 'Extract merchant information from this text in JSON format.',
            'line_items' => 'Extract line items from this receipt in JSON format.',
            'summary' => 'Provide a concise summary of this content.',
            'classification' => 'Classify this document type.',
            'tags' => 'Extract relevant tags from this content.',
            'entities' => 'Extract named entities from this text.',
        ];

        $prompt = $fallbackPrompts[$templateName] ?? 'Analyze this content and provide structured information.';

        return [
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'template_name' => $templateName,
            'schema' => $this->getSchema($templateName, $options),
            'options' => $this->getTemplateOptions($templateName, $options),
        ];
    }

    /**
     * Get template-specific options
     */
    protected function getTemplateOptions(string $templateName, array $userOptions = []): array
    {
        $defaults = [
            'receipt' => [
                'temperature' => 0.1,
                'max_tokens' => 2048,
                'response_format' => 'json_schema',
            ],
            'document' => [
                'temperature' => 0.2,
                'max_tokens' => 3000,
                'response_format' => 'json_schema',
            ],
            'merchant' => [
                'temperature' => 0.1,
                'max_tokens' => 300,
                'response_format' => 'json_object',
            ],
            'summary' => [
                'temperature' => 0.3,
                'max_tokens' => 300,
            ],
        ];

        return array_merge($defaults[$templateName] ?? [], $userOptions);
    }

    // Schema methods for different templates
    protected function getReceiptSchema(array $options = []): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'merchant' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'org_number' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'address', 'org_number', 'phone', 'category'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                            'unit_price' => ['type' => 'number'],
                            'total_price' => ['type' => 'number'],
                            'vat_rate' => ['type' => 'number'],
                        ],
                        'required' => ['name', 'quantity', 'unit_price', 'total_price', 'vat_rate'],
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'subtotal' => ['type' => 'number'],
                        'tax_amount' => ['type' => 'number'],
                        'total_amount' => ['type' => 'number'],
                    ],
                    'required' => ['subtotal', 'tax_amount', 'total_amount'],
                ],
                'receipt_info' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'date' => ['type' => 'string'],
                        'time' => ['type' => 'string'],
                        'receipt_number' => ['type' => 'string'],
                    ],
                    'required' => ['date', 'time', 'receipt_number'],
                ],
                'payment' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'method' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                    ],
                    'required' => ['method', 'currency'],
                ],
            ],
            'required' => ['merchant', 'items', 'totals', 'receipt_info', 'payment'],
        ];
    }

    protected function getDocumentSchema(array $options = []): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'document_type' => [
                    'type' => 'string',
                    'enum' => ['invoice', 'contract', 'report', 'letter', 'memo', 'presentation', 'spreadsheet', 'email', 'legal', 'financial', 'technical', 'other'],
                ],
                'summary' => ['type' => 'string'],
                'entities' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'people' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'organizations' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'locations' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'dates' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'amounts' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['people', 'organizations', 'locations', 'dates', 'amounts'],
                ],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'language' => ['type' => 'string'],
            ],
            'required' => ['title', 'document_type', 'summary', 'entities', 'tags', 'language'],
        ];
    }

    protected function getDefaultSchema(string $templateName): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'result' => ['type' => 'string'],
            ],
            'required' => ['result'],
        ];
    }
}
