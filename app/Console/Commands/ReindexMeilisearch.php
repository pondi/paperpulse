<?php

namespace App\Console\Commands;

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use Illuminate\Console\Command;

class ReindexMeilisearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:reindex-all {--fresh : Clear the index before reindexing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex all searchable models in Meilisearch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $models = [
            Receipt::class,
            LineItem::class,
            Merchant::class,
        ];

        if ($this->option('fresh')) {
            $this->info('Clearing existing indexes...');
            foreach ($models as $model) {
                $this->call('scout:flush', ['model' => $model]);
            }
        }

        $this->info('Starting reindexing of all searchable models...');

        foreach ($models as $model) {
            $modelName = class_basename($model);
            $this->info("Reindexing {$modelName}...");

            $this->call('scout:import', ['model' => $model]);

            $count = $model::count();
            $this->info("✓ {$modelName} reindexed ({$count} records)");
        }

        $this->info('All models have been reindexed successfully!');
    }
}
