<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;
use RuntimeException;
use Throwable;

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
    public function handle(Client $client): int
    {
        try {
            foreach (config('scout.meilisearch.index-settings') as $model => $settings) {
                $instance = new $model;
                if (config('scout.soft_delete')) {
                    $settings['filterableAttributes'][] = '__soft_deleted';
                }
                $task = $client->index($instance->indexableAs())->updateSettings($settings);
                $result = $client->waitForTask($task['taskUid'], 60000);
                if ($result['status'] !== 'succeeded') {
                    throw new RuntimeException('Index settings failed: '.($result['error']['message'] ?? $result['status']));
                }
            }

            $this->info('Meilisearch indices configured successfully!');
            $this->line('Re-import your data with: php artisan scout:reindex-all');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
