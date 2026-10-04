<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReadinessCheck
{
    public function __construct(private DatabaseManager $database, private Migrator $migrator) {}

    /** @return array{status: string, components: array<string, array<string, int|float|string>>} */
    public function check(): array
    {
        $components = [];
        $connection = null;
        $failed = false;

        foreach (config('health.required', ['database', 'migrations', 'queue']) as $service) {
            $start = microtime(true);
            try {
                $healthy = match ($service) {
                    'database' => ($connection ??= $this->boundedConnection())->select('SELECT 1') !== [],
                    'migrations' => $this->migrationsAreCurrent($connection ??= $this->boundedConnection()),
                    'redis' => (bool) Redis::connection('health')->ping(),
                    'meilisearch' => $this->searchIsReady(),
                    'queue' => $this->queueIsReady($connection),
                    default => false,
                };
                $components[$service] = [
                    'status' => $healthy ? 'ok' : 'down',
                    'latency_ms' => round((microtime(true) - $start) * 1000),
                ];
                $failed = $failed || ! $healthy;
            } catch (Throwable) {
                $components[$service] = ['status' => 'down'];
                $failed = true;
            }
        }

        return ['status' => $failed ? 'down' : 'ok', 'components' => $components];
    }

    private function migrationsAreCurrent(Connection $connection): bool
    {
        $table = config('database.migrations.table', 'migrations');
        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return false;
        }

        $files = $this->migrator->getMigrationFiles(array_merge(
            [database_path('migrations')],
            $this->migrator->paths(),
        ));

        return array_diff(array_keys($files), $connection->table($table)->pluck('migration')->all()) === [];
    }

    private function boundedConnection(): Connection
    {
        $default = $this->database->connection();
        $configuration = $default->getConfig();
        if ($default->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Unsupported readiness database driver.');
        }

        $pdo = (new HealthPostgresConnector)->connect($configuration);

        return new PostgresConnection($pdo, $configuration['database'], $configuration['prefix'] ?? '', $configuration);
    }

    private function queueIsReady(?Connection $connection): bool
    {
        $driver = config('queue.connections.'.config('queue.default').'.driver');
        if ($driver === 'redis') {
            $queue = config('queue.connections.redis.queue', 'default');
            $redis = Redis::connection('health');

            return $redis->llen('queues:'.$queue) >= 0;
        }
        if ($driver === 'database') {
            $configuration = config('queue.connections.database');
            if (($configuration['connection'] ?? config('database.default')) !== config('database.default')) {
                return false;
            }

            return ($connection ?? $this->boundedConnection())->table($configuration['table'])
                ->where('queue', $configuration['queue'])->limit(1)->get() !== null;
        }

        return in_array($driver, ['sync', 'null'], true);
    }

    private function searchIsReady(): bool
    {
        $host = config('scout.meilisearch.host');

        return is_string($host) && $host !== ''
            && Http::connectTimeout(1)->timeout(2)->get(rtrim($host, '/').'/health')->successful();
    }
}
