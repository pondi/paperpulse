<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Receipt;
use Exception;
use Illuminate\Console\Command;
use MeiliSearch\Client;

class ConfigureMeilisearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:configure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Meilisearch indices with proper filterable and sortable attributes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Configuring Meilisearch indices...');

        try {
            $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

            // Configure Receipt index
            $this->info('Configuring receipts index...');
            $receiptsIndex = $client->index((new Receipt)->searchableAs());

            $receiptsIndex->updateSettings([
                'filterableAttributes' => [
                    'id',
                    'user_id',
                    'merchant_id',
                    'receipt_category',
                    'receipt_date',
                    'total_amount',
                    'vendors',
                    'created_at',
                    'updated_at',
                ],
                'sortableAttributes' => [
                    'receipt_date',
                    'total_amount',
                    'created_at',
                    'updated_at',
                ],
                'searchableAttributes' => [
                    'merchant_name',
                    'merchant_address',
                    'receipt_description',
                    'note',
                    'summary',
                    'line_items',
                    'vendors',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
            ]);

            $this->info('✓ Receipts index configured');

            // Configure Document index
            $this->info('Configuring documents index...');
            $documentsIndex = $client->index((new Document)->searchableAs());

            $documentsIndex->updateSettings([
                'filterableAttributes' => [
                    'id',
                    'user_id',
                    'category_id',
                    'document_type',
                    'language',
                    'created_at',
                    'updated_at',
                    'document_date',
                ],
                'sortableAttributes' => [
                    'created_at',
                    'updated_at',
                    'document_date',
                    'title',
                ],
                'searchableAttributes' => [
                    'title',
                    'description',
                    'summary',
                    'note',
                    'extracted_text',
                    'entities',
                    'tags',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
            ]);

            $this->info('✓ Documents index configured');

            $this->newLine();
            $this->info('Meilisearch indices configured successfully!');
            $this->newLine();
            $this->warn('Now you should re-import your data:');
            $this->line('  php artisan scout:import "App\Models\Receipt"');
            $this->line('  php artisan scout:import "App\Models\Document"');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error configuring Meilisearch: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
