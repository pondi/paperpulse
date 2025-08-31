<?php

namespace App\Console\Commands;

use App\Services\AI\AIServiceFactory;
use App\Services\AI\ModelConfigurationService;
use Illuminate\Console\Command;

class ManageAIModels extends Command
{
    protected $signature = 'ai:models 
                           {action : Action to perform (list, test, benchmark, optimize)}
                           {--provider= : Filter by provider}
                           {--task= : Task to optimize for}
                           {--budget= : Budget constraint}
                           {--quality= : Quality requirement}';

    protected $description = 'Manage AI models and configurations';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listModels(),
            'test' => $this->testModels(),
            'benchmark' => $this->benchmarkModels(),
            'optimize' => $this->optimizeModelSelection(),
            default => $this->error("Unknown action: {$action}")
        };
    }

    protected function listModels(): int
    {
        $modelService = app(ModelConfigurationService::class);
        $provider = $this->option('provider');

        $models = $modelService->getAvailableModels($provider);

        if (empty($models)) {
            $this->info('No models found.');

            return self::SUCCESS;
        }

        $tableData = array_map(function ($model) {
            return [
                $model->name,
                $model->provider,
                '$'.number_format($model->inputCostPerMToken, 2),
                '$'.number_format($model->outputCostPerMToken, 2),
                number_format($model->contextWindow),
                number_format($model->qualityRating, 1),
                number_format($model->speedRating, 1),
                number_format($model->costEfficiency, 1),
            ];
        }, $models);

        $this->table([
            'Name', 'Provider', 'Input Cost/M', 'Output Cost/M', 'Context', 'Quality', 'Speed', 'Cost Eff',
        ], $tableData);

        return self::SUCCESS;
    }

    protected function testModels(): int
    {
        $provider = $this->option('provider');
        $task = $this->option('task') ?? 'receipt';

        $sampleContent = $this->getSampleContent($task);

        $this->info("Testing models for task: {$task}");

        try {
            if ($provider) {
                $service = AIServiceFactory::create($provider);
                $this->testSingleModel($service, $sampleContent, $task);
            } else {
                // Test all providers
                foreach (['openai', 'anthropic'] as $providerName) {
                    try {
                        $this->line("\n--- Testing {$providerName} ---");
                        $service = AIServiceFactory::create($providerName);
                        $this->testSingleModel($service, $sampleContent, $task);
                    } catch (\Exception $e) {
                        $this->error("Failed to test {$providerName}: {$e->getMessage()}");
                    }
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Testing failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function testSingleModel($service, string $content, string $task): void
    {
        $startTime = microtime(true);

        try {
            $result = match ($task) {
                'receipt' => $service->analyzeReceipt($content),
                'document' => $service->analyzeDocument($content),
                default => throw new \Exception("Unknown task: {$task}")
            };

            $duration = (microtime(true) - $startTime) * 1000;

            $this->line("Provider: {$result['provider']}");
            $this->line("Model: {$result['model']}");
            $this->line('Success: '.($result['success'] ? 'Yes' : 'No'));
            $this->line('Duration: '.number_format($duration, 2).'ms');

            if (isset($result['tokens_used'])) {
                $this->line("Tokens: {$result['tokens_used']}");
            }

            if (isset($result['cost_estimate'])) {
                $this->line('Cost: $'.number_format($result['cost_estimate'], 4));
            }

            if (! $result['success']) {
                $this->error("Error: {$result['error']}");
            }

        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
        }
    }

    protected function benchmarkModels(): int
    {
        $this->info('Model benchmarking is not yet implemented.');

        return self::SUCCESS;
    }

    protected function optimizeModelSelection(): int
    {
        $task = $this->option('task') ?? $this->ask('What task?', 'receipt');
        $budget = $this->option('budget') ?? $this->choice('Budget level?', ['economy', 'standard', 'premium'], 1);
        $quality = $this->option('quality') ?? $this->choice('Quality level?', ['basic', 'standard', 'high', 'premium'], 2);

        $requirements = [
            'task' => $task,
            'budget' => $budget,
            'quality' => $quality,
        ];

        $this->info('Finding optimal model for:');
        $this->line("Task: {$task}");
        $this->line("Budget: {$budget}");
        $this->line("Quality: {$quality}");

        try {
            $model = AIServiceFactory::getOptimalModelForTask($task, $requirements);

            $this->info("\nOptimal Model:");
            $this->line("Name: {$model->name}");
            $this->line("Provider: {$model->provider}");
            $this->line('Quality Rating: '.number_format($model->qualityRating, 1));
            $this->line('Speed Rating: '.number_format($model->speedRating, 1));
            $this->line('Cost Efficiency: '.number_format($model->costEfficiency, 1));
            $this->line('Input Cost: $'.number_format($model->inputCostPerMToken, 2).'/M tokens');
            $this->line('Output Cost: $'.number_format($model->outputCostPerMToken, 2).'/M tokens');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Optimization failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getSampleContent(string $task): string
    {
        return match ($task) {
            'receipt' => "REMA 1000\nStorgata 1, 0123 Oslo\nOrg.nr: 123456789\n\nBrød: 25.00 kr\nMelk: 18.50 kr\n\nTotalt: 43.50 kr\nDato: 2024-01-15\nTid: 14:30",
            'document' => "MEETING MINUTES\nDate: January 15, 2024\nAttendees: John Smith, Jane Doe\n\nDiscussed quarterly budget allocation for Q1 2024. Approved increase in marketing spend by 15%.",
            default => 'Sample content for testing'
        };
    }
}
