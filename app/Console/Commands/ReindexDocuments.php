<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;

class ReindexDocuments extends Command
{
    protected $signature = 'reindex:documents';

    protected $description = 'Reindex all documents in Meilisearch';

    public function handle()
    {
        $this->info('Starting reindexing of documents...');

        // Get total count
        $total = Document::count();
        $this->info("Found {$total} documents to reindex");

        // Delete existing index
        Document::removeAllFromSearch();
        $this->info('Cleared existing index');

        // Reindex in chunks to avoid memory issues
        Document::chunkById(100, function ($documents) {
            $documents->each->searchable();
            $this->info('Indexed '.$documents->count().' documents');
        });

        $this->info('Reindexing completed successfully!');

        return Command::SUCCESS;
    }
}
