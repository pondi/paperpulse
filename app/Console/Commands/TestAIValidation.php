<?php

namespace App\Console\Commands;

use App\Services\AI\AIServiceFactory;
use Illuminate\Console\Command;

class TestAIValidation extends Command
{
    protected $signature = 'ai:test-validation 
                           {--type=receipt : Type to test (receipt, document)}
                           {--provider=openai : AI provider to use}
                           {--sample= : Sample text file to test}';

    protected $description = 'Test AI output validation';

    public function handle(): int
    {
        $type = $this->option('type');
        $provider = $this->option('provider');
        $sampleFile = $this->option('sample');

        if ($sampleFile && ! file_exists($sampleFile)) {
            $this->error("Sample file not found: {$sampleFile}");

            return self::FAILURE;
        }

        $content = $sampleFile
            ? file_get_contents($sampleFile)
            : $this->getSampleContent($type);

        $this->info("Testing {$type} validation with {$provider} provider...");

        try {
            $aiService = AIServiceFactory::create($provider);

            $result = match ($type) {
                'receipt' => $aiService->analyzeReceipt($content),
                'document' => $aiService->analyzeDocument($content),
                default => throw new \Exception("Unknown type: {$type}")
            };

            $this->displayResults($result, $type);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getSampleContent(string $type): string
    {
        return match ($type) {
            'receipt' => "REMA 1000\nStorgata 1, 0123 Oslo\nOrg.nr: 123456789\n\nBrød: 25.00 kr\nMelk: 18.50 kr\n\nTotalt: 43.50 kr\nDato: 2024-01-15\nTid: 14:30",
            'document' => "MEETING MINUTES\nDate: January 15, 2024\nAttendees: John Smith, Jane Doe\n\nDiscussed quarterly budget allocation for Q1 2024. Approved increase in marketing spend by 15%. Next meeting scheduled for February 1st.",
            default => 'Sample content for testing'
        };
    }

    protected function displayResults(array $result, string $type): void
    {
        $this->info('=== Results ===');
        $this->line('Success: '.($result['success'] ? 'Yes' : 'No'));
        $this->line('Provider: '.($result['provider'] ?? 'Unknown'));

        if (isset($result['validation'])) {
            $validation = $result['validation'];
            $this->line('Validation Passed: '.($validation['passed'] ? 'Yes' : 'No'));

            if (! empty($validation['warnings'])) {
                $this->warn('Warnings:');
                foreach ($validation['warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            if (isset($validation['error'])) {
                $this->error('Validation Error: '.$validation['error']);
            }
        }

        if ($result['success'] && isset($result['data'])) {
            $this->info("\n=== Extracted Data ===");
            $this->line(json_encode($result['data'], JSON_PRETTY_PRINT));
        }

        if (! $result['success']) {
            $this->error('Error: '.($result['error'] ?? 'Unknown error'));
        }
    }
}
