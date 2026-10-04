<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

class MigrationLock
{
    private ?Connection $connection = null;

    /** @var resource|null */
    private $handle = null;

    public function __construct(private DatabaseManager $database) {}

    public function acquire(): bool
    {
        if ($this->connection !== null || is_resource($this->handle)) {
            return true;
        }

        $connection = $this->database->connection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection = $this->database->build([...$connection->getConfig(), 'name' => 'migration_lock_'.spl_object_id($this)]);
            if (! $connection->selectOne('SELECT pg_try_advisory_lock(?, ?) AS acquired', [190126, 47])->acquired) {
                $connection->disconnect();

                return false;
            }
            $this->connection = $connection;

            return true;
        }

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Safe migrations support PostgreSQL and local SQLite only.');
        }

        $handle = fopen(config('migrations.lock_path'), 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open the migration lock.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }
        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->connection !== null) {
            $this->connection->selectOne('SELECT pg_advisory_unlock(?, ?)', [190126, 47]);
            $this->connection->disconnect();
            $this->connection = null;
        }
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
