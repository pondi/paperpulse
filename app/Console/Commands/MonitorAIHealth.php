<?php

namespace App\Console\Commands;

use App\Services\AI\HealthMonitoringService;
use Illuminate\Console\Command;

class MonitorAIHealth extends Command
{
    protected $signature = 'ai:monitor 
                           {--continuous : Run continuous monitoring}
                           {--interval=60 : Monitoring interval in seconds}
                           {--alerts : Show recent alerts}';

    protected $description = 'Monitor AI service health';

    public function handle(): int
    {
        $healthService = app(HealthMonitoringService::class);

        if ($this->option('alerts')) {
            return $this->showAlerts($healthService);
        }

        if ($this->option('continuous')) {
            return $this->continuousMonitoring($healthService);
        }

        // One-time health check
        $healthService->monitorHealth();
        $this->info('Health monitoring completed. Check logs for details.');

        return self::SUCCESS;
    }

    protected function showAlerts(HealthMonitoringService $service): int
    {
        $alerts = $service->getRecentAlerts(24);

        if (empty($alerts)) {
            $this->info('No recent alerts found.');

            return self::SUCCESS;
        }

        $this->info(count($alerts).' alerts in the last 24 hours:');

        $tableData = array_map(function ($alert) {
            return [
                $alert['id'],
                $alert['provider'],
                $alert['severity'],
                substr($alert['title'], 0, 40).'...',
                $alert['created_at'],
            ];
        }, $alerts);

        $this->table(['ID', 'Provider', 'Severity', 'Title', 'Created'], $tableData);

        return self::SUCCESS;
    }

    protected function continuousMonitoring(HealthMonitoringService $service): int
    {
        $interval = (int) $this->option('interval');

        $this->info("Starting continuous monitoring (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop...');

        while (true) {
            $service->monitorHealth();
            $this->line('['.now()->format('Y-m-d H:i:s').'] Health check completed');

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
