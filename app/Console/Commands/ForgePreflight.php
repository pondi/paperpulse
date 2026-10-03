<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class ForgePreflight extends Command
{
    protected $signature = 'forge:preflight';

    protected $description = 'Validate the native Forge runtime before deployment';

    public function handle(DatabaseManager $database): int
    {
        if ($database->connection()->getDriverName() !== 'pgsql') {
            $this->error('Forge requires PostgreSQL. Set DB_CONNECTION=pgsql.');

            return self::FAILURE;
        }

        $this->info('PostgreSQL configuration verified.');

        return self::SUCCESS;
    }
}
