<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

class MigrationLock
{
    private ?Connection $connection = null;

    public function __construct(private DatabaseManager $database) {}

    public function acquire(): bool
    {
        if ($this->connection !== null) {
            return true;
        }

        $connection = $this->database->connection();

        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Safe migrations require PostgreSQL.');
        }
        /** @var Connection $connection */
        $connection = $this->database->build([...$connection->getConfig(), 'name' => 'migration_lock_'.spl_object_id($this)]);
        if (! $connection->selectOne('SELECT pg_try_advisory_lock(?, ?) AS acquired', [190126, 47])->acquired) {
            $connection->disconnect();

            return false;
        }
        $this->connection = $connection;

        return true;
    }

    public function release(): void
    {
        if ($this->connection !== null) {
            $this->connection->selectOne('SELECT pg_advisory_unlock(?, ?)', [190126, 47]);
            $this->connection->disconnect();
            $this->connection = null;
        }
    }
}
