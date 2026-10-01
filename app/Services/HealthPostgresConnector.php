<?php

namespace App\Services;

use Illuminate\Database\Connectors\PostgresConnector;

class HealthPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config): string
    {
        return parent::getDsn($config).";connect_timeout=2;options='-c statement_timeout=2000 -c lock_timeout=1000'";
    }
}
