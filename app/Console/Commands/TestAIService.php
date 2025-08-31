<?php

namespace App\Console\Commands;

use App\Services\AI\AIServiceFactory;
use Illuminate\Console\Command;

class TestAIService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test {provider=openai} {--receipt} {--document}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI service providers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = $this->argument('provider');

        $this->info("Testing AI provider: {$provider}");

        try {
            $aiService = AIServiceFactory::create($provider);

            $this->info('✓ AI service created successfully');
            $this->info('Provider: '.$aiService->getProviderName());

            if ($this->option('receipt')) {
                $this->testReceiptAnalysis($aiService);
            }

            if ($this->option('document')) {
                $this->testDocumentAnalysis($aiService);
            }

            if (! $this->option('receipt') && ! $this->option('document')) {
                $this->info('Use --receipt or --document to test specific functionality');
            }

        } catch (\Exception $e) {
            $this->error('Failed to create AI service: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function testReceiptAnalysis($aiService)
    {
        $this->info("\nTesting receipt analysis...");

        $sampleReceipt = 'REMA 1000 OSLO
Grensen 5-7
0159 OSLO
Org.nr: 987654321
Tlf: 22334455

KVITTERING

Melk 1L           25.90
Brød              34.50
Ost 200g          89.90
-----------------------
SUM             150.30
MVA 15%          22.55
TOTALT          150.30

Betalt kontant  200.00
Tilbake          49.70

Dato: 08.01.2025 14:32
Kvitt.nr: 12345

Takk for handelen!';

        try {
            $result = $aiService->analyzeReceipt($sampleReceipt);

            if ($result['success']) {
                $this->info('✓ Receipt analysis successful');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Merchant', $result['data']['merchant']['name'] ?? 'N/A'],
                        ['Total', $result['data']['totals']['total'] ?? 'N/A'],
                        ['Date', $result['data']['date'] ?? 'N/A'],
                        ['Items', count($result['data']['items'] ?? [])],
                    ]
                );
            } else {
                $this->error('Receipt analysis failed: '.$result['error']);
            }
        } catch (\Exception $e) {
            $this->error('Receipt analysis error: '.$e->getMessage());
        }
    }

    private function testDocumentAnalysis($aiService)
    {
        $this->info("\nTesting document analysis...");

        $sampleDocument = 'Software License Agreement

This Software License Agreement ("Agreement") is entered into as of January 8, 2025 
between TechCorp Inc., a Delaware corporation ("Licensor") and ClientCo Ltd., 
a Norwegian company ("Licensee").

1. Grant of License
Licensor hereby grants to Licensee a non-exclusive, non-transferable license to use 
the Software Product version 2.0 for a period of one (1) year.

2. License Fee
The total license fee is USD 50,000 payable within 30 days of signing.

3. Termination
This Agreement may be terminated by either party with 30 days written notice.

Signed by:
John Smith, CEO TechCorp Inc.
Date: January 8, 2025';

        try {
            $result = $aiService->analyzeDocument($sampleDocument);

            if ($result['success']) {
                $this->info('✓ Document analysis successful');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Title', $result['data']['title'] ?? 'N/A'],
                        ['Type', $result['data']['document_type'] ?? 'N/A'],
                        ['Language', $result['data']['language'] ?? 'N/A'],
                        ['Tags', implode(', ', $result['data']['tags'] ?? [])],
                    ]
                );

                if (! empty($result['data']['entities'])) {
                    $this->info("\nExtracted Entities:");
                    foreach ($result['data']['entities'] as $type => $values) {
                        if (! empty($values)) {
                            $this->info("  {$type}: ".implode(', ', $values));
                        }
                    }
                }
            } else {
                $this->error('Document analysis failed: '.$result['error']);
            }
        } catch (\Exception $e) {
            $this->error('Document analysis error: '.$e->getMessage());
        }
    }
}
