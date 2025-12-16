<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;

class ReindexReceipts extends Command
{
    protected $signature = 'reindex:receipts';

    protected $description = 'Reindex all receipts in Meilisearch';

    public function handle()
    {
        $this->info('Starting reindexing of receipts...');

        // Get total count
        $total = Receipt::count();
        $this->info("Found {$total} receipts to reindex");

        // Delete existing index
        Receipt::removeAllFromSearch();
        $this->info('Cleared existing index');

        // Reindex in chunks to avoid memory issues
        Receipt::with(['merchant', 'lineItems'])
            ->chunkById(100, function ($receipts) {
                $receipts->each->searchable();
                $this->info('Indexed '.$receipts->count().' receipts');
            });

        $this->info('Reindexing completed successfully!');

        return Command::SUCCESS;
    }
}
