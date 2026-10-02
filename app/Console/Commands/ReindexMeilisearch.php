<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use RuntimeException;
use Throwable;

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
    public function handle(Client $client): int
    {
        $models = array_keys(config('scout.meilisearch.index-settings'));
        $scoutDriver = config('scout.driver');
        $scoutQueue = config('scout.queue');
        config(['scout.driver' => 'meilisearch', 'scout.queue' => false]);

        try {
            $latestTaskUid = $client->getTasks((new TasksQuery)->setLimit(1))->getResults()[0]['uid'] ?? -1;

            foreach ($models as $model) {
                if ($this->option('fresh')) {
                    if ($this->call('scout:flush', ['model' => $model]) !== self::SUCCESS) {
                        return self::FAILURE;
                    }
                    $this->waitForIndexTasks($client, $model, $latestTaskUid);
                }

                $this->info('Reindexing '.class_basename($model).'...');
                if ($this->call('scout:import', ['model' => $model]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
                $this->waitForIndexTasks($client, $model, $latestTaskUid);
            }

            $this->info('All models have been reindexed successfully!');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            config(['scout.driver' => $scoutDriver, 'scout.queue' => $scoutQueue]);
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function waitForIndexTasks(Client $client, string $model, int $afterTaskUid): void
    {
        $index = $client->index((new $model)->indexableAs());
        $query = (new TasksQuery)->setLimit(100);

        do {
            $tasks = $index->getTasks($query);
            foreach ($tasks->getResults() as $task) {
                if ($task['uid'] <= $afterTaskUid) {
                    return;
                }

                $result = $client->waitForTask($task['uid'], 60000);
                if ($result['status'] !== 'succeeded') {
                    throw new RuntimeException("Index task {$task['uid']} failed: ".($result['error']['message'] ?? $result['status']));
                }
            }
            $query->setFrom($tasks->getNext());
        } while ($tasks->getNext() !== 0);
    }
}
