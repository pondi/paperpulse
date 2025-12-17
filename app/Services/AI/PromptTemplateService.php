<?php

namespace App\Services\AI;

use App\Services\AI\Prompt\FallbackPromptProvider;
use App\Services\AI\Prompt\PromptContentParser;
use App\Services\AI\Prompt\Schema\PromptSchemaResolver;
use App\Services\AI\Prompt\TemplateOptionsProvider;
use App\Services\AI\Prompt\TemplatePathResolver;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Compiles Blade-based prompt templates and resolves schema/options.
 *
 * Supports custom overrides, safe fallbacks, and structured parsing into
 * Chat messages + JSON schema compatible with provider expectations.
 */
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
     * Get compiled prompt for a template.
     *
     * @param  array  $data  Template variables
     * @param  array  $options  Provider/model/options hints
     * @return array{messages:array,template_name:string,schema:array,options:array}
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
                'model' => $options['model'] ?? config('ai.models.default'),
                'provider' => $options['provider'] ?? 'openai',
            ], $data);

            // Render the template
            $renderedContent = View::make($templatePath, $templateData)->render();

            // Parse the rendered content into system and user messages
            return PromptContentParser::parse(
                $renderedContent,
                fn (string $name, array $opts) => $this->getSchema($name, $opts),
                fn (string $name, array $opts) => $this->getTemplateOptions($name, $opts),
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

    /** Resolve the JSON schema for a given template. */
    public function getSchema(string $templateName, array $options = []): array
    {
        return PromptSchemaResolver::forTemplate($templateName, $options);
    }

    /** Register a custom template view path. */
    public function registerTemplate(string $name, string $viewPath): void
    {
        $this->defaultTemplates[$name] = $viewPath;

        // Clear compiled cache for this template
        if (isset($this->compiledTemplates[$name])) {
            unset($this->compiledTemplates[$name]);
        }
    }

    /** Get all available template keys. */
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

    /** Get template-specific options. */
    protected function getTemplateOptions(string $templateName, array $userOptions = []): array
    {
        return TemplateOptionsProvider::forTemplate($templateName, $userOptions);
    }
    // Schema resolution moved to dedicated providers under Prompt\Schema
}
