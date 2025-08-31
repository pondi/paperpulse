<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health {--format=table : Output format: table, json} {--alert : Send alerts for issues}';

    protected $description = 'Check queue health and report status';

    private JobRepository $jobs;

    private MasterSupervisorRepository $supervisors;

    public function __construct(JobRepository $jobs, MasterSupervisorRepository $supervisors)
    {
        parent::__construct();
        $this->jobs = $jobs;
        $this->supervisors = $supervisors;
    }

    public function handle()
    {
        $this->info('Checking queue health...');

        $health = $this->gatherHealthMetrics();

        if ($this->option('format') === 'json') {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));
        } else {
            $this->displayHealthTable($health);
        }

        if ($this->option('alert') && $this->hasIssues($health)) {
            $this->sendAlerts($health);
        }

        return $this->determineExitCode($health);
    }

    private function gatherHealthMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'horizon_status' => $this->checkHorizonStatus(),
            'supervisors' => $this->checkSupervisors(),
            'queues' => $this->checkQueues(),
            'failed_jobs' => $this->checkFailedJobs(),
            'redis_status' => $this->checkRedisStatus(),
            'recent_errors' => $this->getRecentErrors(),
            'performance' => $this->getPerformanceMetrics(),
        ];
    }

    private function checkHorizonStatus(): array
    {
        try {
            $masters = $this->supervisors->all();

            return [
                'running' => ! empty($masters),
                'masters_count' => count($masters),
                'status' => ! empty($masters) ? 'healthy' : 'stopped',
            ];
        } catch (\Exception $e) {
            return [
                'running' => false,
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    private function checkSupervisors(): array
    {
        try {
            $masters = $this->supervisors->all();
            $supervisors = [];

            foreach ($masters as $master) {
                $supervisors[] = [
                    'name' => $master->name,
                    'status' => $master->status,
                    'processes' => $master->processes,
                ];
            }

            return $supervisors;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function checkQueues(): array
    {
        $queues = ['default', 'receipts', 'documents'];
        $queueStats = [];

        foreach ($queues as $queue) {
            try {
                $size = Redis::llen("queues:{$queue}");
                $processing = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->count();

                $queueStats[$queue] = [
                    'pending' => $size,
                    'processing' => $processing,
                    'status' => $size > 100 ? 'overloaded' : ($size > 10 ? 'busy' : 'normal'),
                ];
            } catch (\Exception $e) {
                $queueStats[$queue] = [
                    'error' => $e->getMessage(),
                    'status' => 'error',
                ];
            }
        }

        return $queueStats;
    }

    private function checkFailedJobs(): array
    {
        try {
            $totalFailed = DB::table('failed_jobs')->count();
            $recentFailed = DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHour())
                ->count();

            $failedByQueue = DB::table('failed_jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get()
                ->pluck('count', 'queue')
                ->toArray();

            return [
                'total' => $totalFailed,
                'recent_hour' => $recentFailed,
                'by_queue' => $failedByQueue,
                'status' => $recentFailed > 5 ? 'critical' : ($totalFailed > 10 ? 'warning' : 'healthy'),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function checkRedisStatus(): array
    {
        try {
            $ping = Redis::ping();
            $info = Redis::info();

            return [
                'connected' => $ping === 'PONG',
                'memory_usage' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'status' => $ping === 'PONG' ? 'healthy' : 'error',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    private function getRecentErrors(): array
    {
        try {
            $errors = DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHour())
                ->select('queue', 'exception', 'failed_at')
                ->limit(5)
                ->get()
                ->map(function ($error) {
                    return [
                        'queue' => $error->queue,
                        'time' => $error->failed_at,
                        'error' => substr($error->exception, 0, 100).'...',
                    ];
                })
                ->toArray();

            return $errors;
        } catch (\Exception $e) {
            return [['error' => $e->getMessage()]];
        }
    }

    private function getPerformanceMetrics(): array
    {
        try {
            // Get recent job stats from database instead of Horizon methods
            $recentCompleted = DB::table('jobs')
                ->where('created_at', '>', now()->subHour())
                ->count();

            $recentFailed = DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHour())
                ->count();

            $total = $recentCompleted + $recentFailed;
            $successRate = $total > 0 ? ($recentCompleted / $total) * 100 : 100;

            return [
                'completed_recent' => $recentCompleted,
                'failed_recent' => $recentFailed,
                'success_rate' => round($successRate, 2),
                'status' => $successRate > 95 ? 'excellent' : ($successRate > 85 ? 'good' : 'poor'),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function displayHealthTable(array $health): void
    {
        $this->info('=== Queue Health Report ===');

        // Horizon Status
        $this->table(['Component', 'Status', 'Details'], [
            ['Horizon', $health['horizon_status']['status'], 'Masters: '.($health['horizon_status']['masters_count'] ?? 0)],
            ['Redis', $health['redis_status']['status'], 'Connected: '.($health['redis_status']['connected'] ? 'Yes' : 'No')],
        ]);

        // Queue Status
        $queueRows = [];
        foreach ($health['queues'] as $name => $stats) {
            $queueRows[] = [
                $name,
                $stats['status'] ?? 'unknown',
                'Pending: '.($stats['pending'] ?? 'N/A').', Processing: '.($stats['processing'] ?? 'N/A'),
            ];
        }
        $this->table(['Queue', 'Status', 'Jobs'], $queueRows);

        // Failed Jobs
        $this->info("Failed Jobs: {$health['failed_jobs']['total']} total, {$health['failed_jobs']['recent_hour']} in last hour");

        // Performance
        if (isset($health['performance']['success_rate'])) {
            $this->info("Success Rate: {$health['performance']['success_rate']}%");
        }

        // Recent Errors
        if (! empty($health['recent_errors'])) {
            $this->warn('Recent Errors:');
            foreach ($health['recent_errors'] as $error) {
                if (isset($error['error'])) {
                    $this->line("- {$error['queue']}: {$error['error']}");
                }
            }
        }
    }

    private function hasIssues(array $health): bool
    {
        return $health['horizon_status']['status'] !== 'healthy' ||
               $health['redis_status']['status'] !== 'healthy' ||
               $health['failed_jobs']['status'] === 'critical' ||
               (isset($health['performance']['success_rate']) && $health['performance']['success_rate'] < 85);
    }

    private function sendAlerts(array $health): void
    {
        $issues = [];

        if ($health['horizon_status']['status'] !== 'healthy') {
            $issues[] = 'Horizon is not running properly';
        }

        if ($health['failed_jobs']['status'] === 'critical') {
            $issues[] = "High number of failed jobs: {$health['failed_jobs']['recent_hour']} in the last hour";
        }

        if (! empty($issues)) {
            Log::critical('Queue health issues detected', [
                'issues' => $issues,
                'health_report' => $health,
            ]);

            $this->error('ALERT: Queue health issues detected:');
            foreach ($issues as $issue) {
                $this->error("- {$issue}");
            }
        }
    }

    private function determineExitCode(array $health): int
    {
        if ($health['horizon_status']['status'] !== 'healthy') {
            return 2; // Critical error
        }

        if ($health['failed_jobs']['status'] === 'critical') {
            return 1; // Warning
        }

        return 0; // Success
    }
}
