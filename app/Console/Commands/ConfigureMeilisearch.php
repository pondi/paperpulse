<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
    public function handle(): int
    {
        $this->info('Configuring Meilisearch indices via scout:sync-index-settings...');
        $this->newLine();

        $exitCode = $this->call('scout:sync-index-settings');

        if ($exitCode === Command::SUCCESS) {
            $this->newLine();
            $this->info('Meilisearch indices configured successfully!');
            $this->newLine();
            $this->warn('Now you should re-import your data:');
            $this->line('  php artisan scout:reindex-all');
        }

        return $exitCode;
    }
}
