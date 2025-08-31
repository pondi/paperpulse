<?php

namespace App\Console\Commands;

use App\Services\AI\AIServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestDebugLogging extends Command
{
    protected $signature = 'debug:test-logging';

    protected $description = 'Test debug logging functionality for AI processing';

    public function handle()
    {
        $this->info('Testing debug logging functionality...');

        // Test basic debug logging
        $this->info('1. Testing basic debug logging...');
        Log::debug('[TestDebug] Basic debug log entry', [
            'test_type' => 'basic',
            'debug_enabled' => config('app.debug'),
            'timestamp' => now()->toISOString(),
        ]);

        // Test AI service debug logging with sample data
        $this->info('2. Testing AI service debug logging...');

        try {
            $aiService = AIServiceFactory::create();

            // Test with a simple sample receipt content
            $sampleContent = "CAFENAIS AIVARA\nKvittering\nDato: 2023-01-15\nTid: 14:30\nKaffe: 45.00 NOK\nCroissant: 35.00 NOK\nTotalt: 80.00 NOK\nMVA: 16.00 NOK\nTakk for handelen!";

            Log::info('[TestDebug] Starting AI service test with sample receipt');

            $result = $aiService->analyzeReceipt($sampleContent, [
                'debug' => true,
                'test_mode' => true,
            ]);

            $this->info('AI Service Test Result:');
            $this->info('Success: '.($result['success'] ? 'Yes' : 'No'));
            $this->info('Provider: '.($result['provider'] ?? 'Unknown'));
            $this->info('Model: '.($result['model'] ?? 'Unknown'));
            $this->info('Tokens used: '.($result['tokens_used'] ?? 0));

            if (isset($result['fallback_used'])) {
                $this->info('Fallback used: '.($result['fallback_used'] ? 'Yes' : 'No'));
            }

            if (isset($result['error'])) {
                $this->error('Error: '.$result['error']);
            }

        } catch (\Exception $e) {
            $this->error('AI Service test failed: '.$e->getMessage());
        }

        $this->info('3. Checking recent logs...');

        // Check if debug logs were written
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $recentLogs = shell_exec("tail -20 {$logFile} | grep 'TestDebug\\|\\[OpenAI\\]\\|\\[Anthropic\\]'");

            if ($recentLogs) {
                $this->info('Recent debug log entries found:');
                $this->line($recentLogs);
            } else {
                $this->warn('No recent debug log entries found. Check if APP_DEBUG=true in .env');
            }
        } else {
            $this->warn('Laravel log file not found at: '.$logFile);
        }

        $this->info('Debug logging test completed!');
        $this->info('');
        $this->info('To see all debug logs in real-time, run:');
        $this->info('tail -f storage/logs/laravel.log | grep -E "\\[OpenAI\\]|\\[Anthropic\\]|\\[ReceiptAnalysis\\]|\\[ProcessReceipt\\]"');

        return 0;
    }
}
