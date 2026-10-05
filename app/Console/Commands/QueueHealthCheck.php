<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health {--format=table : Output format: table, json} {--alert : Log critical queue issues}';

    protected $description = 'Report database queue backlog and failed jobs';

    public function handle(DatabaseManager $database): int
    {
        if (config('queue.default') !== 'database') {
            $this->error('Queue health requires the supported database queue connection.');

            return self::FAILURE;
        }
        try {
            $connection = $database->connection(config('queue.connections.database.connection'));
            $queues = [];
            $timestamp = now()->timestamp;
            foreach (config('queue.worker_queues') as $queue) {
                $jobs = $connection->table(config('queue.connections.database.table'))->where('queue', $queue);
                $unreserved = (clone $jobs)->whereNull('reserved_at');
                $ready = (clone $unreserved)->where('available_at', '<=', $timestamp)->count();
                $delayed = (clone $unreserved)->where('available_at', '>', $timestamp)->count();
                $queues[$queue] = [
                    'pending' => $ready + $delayed,
                    'ready' => $ready,
                    'delayed' => $delayed,
                    'processing' => (clone $jobs)->whereNotNull('reserved_at')->count(),
                ];
            }
            $failed = $database->connection(config('queue.failed.database'))->table(config('queue.failed.table'));
            $health = [
                'queues' => $queues,
                'failed_jobs' => ['total' => $failed->count(), 'recent_hour' => (clone $failed)->where('failed_at', '>', now()->subHour())->count()],
            ];
        } catch (Throwable) {
            $this->error('Database queue storage is unavailable.');

            return self::FAILURE;
        }
        $critical = $health['failed_jobs']['recent_hour'] > 5;
        if ($this->option('format') === 'json') {
            $this->line(json_encode($health, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Queue', 'Pending', 'Ready', 'Delayed', 'Processing'], collect($queues)->map(fn ($stats, $queue) => [$queue, $stats['pending'], $stats['ready'], $stats['delayed'], $stats['processing']])->all());
            $this->info('Failed jobs: '.$health['failed_jobs']['total']);
        }
        if ($critical && $this->option('alert')) {
            Log::critical('Database queue has more than five failed jobs in the last hour.', $health);
        }

        return $critical ? self::FAILURE : self::SUCCESS;
    }
}
