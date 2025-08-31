<?php

namespace App\Console\Commands;

use App\Services\AI\PromptTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ManagePromptTemplates extends Command
{
    protected $signature = 'prompts:manage 
                           {action : Action to perform (list, create, validate)}
                           {--name= : Template name}
                           {--type= : Template type}';

    protected $description = 'Manage AI prompt templates';

    public function handle(): int
    {
        $action = $this->argument('action');
        $promptService = app(PromptTemplateService::class);

        return match ($action) {
            'list' => $this->listTemplates($promptService),
            'create' => $this->createTemplate(),
            'validate' => $this->validateTemplates($promptService),
            default => $this->error("Unknown action: {$action}")
        };
    }

    protected function listTemplates(PromptTemplateService $service): int
    {
        $templates = $service->getAvailableTemplates();

        $this->info('Available Templates:');
        $this->table(['Name', 'Path', 'Exists'], array_map(function ($name) {
            $path = "resources/views/ai/prompts/{$name}.blade.php";
            $exists = File::exists(base_path($path));

            return [$name, $path, $exists ? '✓' : '✗'];
        }, $templates));

        return self::SUCCESS;
    }

    protected function createTemplate(): int
    {
        $name = $this->option('name') ?? $this->ask('Template name?');
        $type = $this->option('type') ?? $this->choice('Template type?', [
            'receipt', 'document', 'merchant', 'summary', 'classification',
        ]);

        $path = resource_path("views/ai/prompts/{$name}.blade.php");

        if (File::exists($path)) {
            if (! $this->confirm("Template {$name} already exists. Overwrite?")) {
                return self::FAILURE;
            }
        }

        $stub = $this->getTemplateStub($type);
        File::put($path, $stub);

        $this->info("Template created: {$path}");

        return self::SUCCESS;
    }

    protected function validateTemplates(PromptTemplateService $service): int
    {
        $templates = $service->getAvailableTemplates();
        $errors = [];

        foreach ($templates as $template) {
            try {
                $prompt = $service->getPrompt($template, ['content' => 'test']);
                $schema = $service->getSchema($template);

                if (empty($prompt['messages'])) {
                    $errors[] = "Template {$template}: No messages generated";
                }

                if (empty($schema)) {
                    $errors[] = "Template {$template}: No schema defined";
                }

            } catch (\Exception $e) {
                $errors[] = "Template {$template}: {$e->getMessage()}";
            }
        }

        if (empty($errors)) {
            $this->info('All templates are valid!');

            return self::SUCCESS;
        }

        $this->error('Template validation errors:');
        foreach ($errors as $error) {
            $this->line("- {$error}");
        }

        return self::FAILURE;
    }

    protected function getTemplateStub(string $type): string
    {
        return match ($type) {
            'receipt' => $this->getReceiptStub(),
            'document' => $this->getDocumentStub(),
            default => $this->getBasicStub()
        };
    }

    protected function getReceiptStub(): string
    {
        return <<<'BLADE'
<system>
Expert receipt analysis prompt for {{ $template_name }}.
</system>

<user>
Analyze this receipt:

{{ $content }}
</user>
BLADE;
    }

    protected function getDocumentStub(): string
    {
        return <<<'BLADE'
<system>
Expert document analysis prompt for {{ $template_name }}.
</system>

<user>
Analyze this document:

{{ $content }}
</user>
BLADE;
    }

    protected function getBasicStub(): string
    {
        return <<<'BLADE'
<system>
AI prompt for {{ $template_name }}.
</system>

<user>
Process this content:

{{ $content }}
</user>
BLADE;
    }
}
