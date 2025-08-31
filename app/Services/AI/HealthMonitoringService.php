<?php

namespace App\Services\AI;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HealthMonitoringService
{
    protected array $healthThresholds;

    protected array $alertChannels;

    public function __construct()
    {
        $this->healthThresholds = config('ai.health_monitoring.thresholds', [
            'error_rate_threshold' => 0.2, // 20% error rate
            'response_time_threshold' => 30000, // 30 seconds
            'availability_threshold' => 0.95, // 95% availability
        ]);

        $this->alertChannels = config('ai.health_monitoring.alert_channels', ['log']);
    }

    /**
     * Monitor system health and trigger alerts
     */
    public function monitorHealth(): void
    {
        try {
            $providers = ['openai', 'anthropic'];
            $alerts = [];

            foreach ($providers as $provider) {
                $health = $this->checkProviderHealth($provider);

                if (! $health['healthy']) {
                    $alerts[] = $this->createAlert($provider, $health);
                }
            }

            if (! empty($alerts)) {
                $this->sendAlerts($alerts);
            }

            // Store overall health metrics
            Cache::put('ai_system_health', [
                'timestamp' => now()->toISOString(),
                'providers' => array_combine($providers, array_map([$this, 'checkProviderHealth'], $providers)),
                'alerts' => $alerts,
            ], now()->addHours(1));

        } catch (Exception $e) {
            Log::error('[HealthMonitoringService] Health monitoring failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check health of specific provider
     */
    protected function checkProviderHealth(string $provider): array
    {
        $metrics = $this->getProviderMetrics($provider);
        $health = [
            'provider' => $provider,
            'healthy' => true,
            'issues' => [],
            'metrics' => $metrics,
            'checked_at' => now()->toISOString(),
        ];

        // Check error rate
        if ($metrics['error_rate'] > $this->healthThresholds['error_rate_threshold']) {
            $health['healthy'] = false;
            $health['issues'][] = [
                'type' => 'high_error_rate',
                'value' => $metrics['error_rate'],
                'threshold' => $this->healthThresholds['error_rate_threshold'],
                'severity' => 'high',
            ];
        }

        // Check response time
        if ($metrics['avg_response_time'] > $this->healthThresholds['response_time_threshold']) {
            $health['healthy'] = false;
            $health['issues'][] = [
                'type' => 'slow_response',
                'value' => $metrics['avg_response_time'],
                'threshold' => $this->healthThresholds['response_time_threshold'],
                'severity' => 'medium',
            ];
        }

        // Check availability
        if ($metrics['availability'] < $this->healthThresholds['availability_threshold']) {
            $health['healthy'] = false;
            $health['issues'][] = [
                'type' => 'low_availability',
                'value' => $metrics['availability'],
                'threshold' => $this->healthThresholds['availability_threshold'],
                'severity' => 'high',
            ];
        }

        // Check for circuit breaker status
        if (Cache::has("circuit_breaker.{$provider}")) {
            $health['issues'][] = [
                'type' => 'circuit_breaker_open',
                'severity' => 'critical',
            ];
        }

        return $health;
    }

    /**
     * Get provider metrics from cache
     */
    protected function getProviderMetrics(string $provider): array
    {
        $cacheKey = "provider_metrics.{$provider}";

        return Cache::get($cacheKey, [
            'error_rate' => 0.0,
            'avg_response_time' => 0,
            'availability' => 1.0,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
        ]);
    }

    /**
     * Create alert for health issue
     */
    protected function createAlert(string $provider, array $health): array
    {
        $severity = $this->determineSeverity($health['issues']);

        return [
            'id' => uniqid('alert_'),
            'provider' => $provider,
            'severity' => $severity,
            'title' => "AI Provider Health Alert: {$provider}",
            'message' => $this->buildAlertMessage($provider, $health),
            'issues' => $health['issues'],
            'metrics' => $health['metrics'],
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Build alert message
     */
    protected function buildAlertMessage(string $provider, array $health): string
    {
        $issues = $health['issues'];
        $issueDescriptions = [];

        foreach ($issues as $issue) {
            $description = match ($issue['type']) {
                'high_error_rate' => "Error rate: {$issue['value']}% (threshold: {$issue['threshold']}%)",
                'slow_response' => "Response time: {$issue['value']}ms (threshold: {$issue['threshold']}ms)",
                'low_availability' => "Availability: {$issue['value']}% (threshold: {$issue['threshold']}%)",
                'circuit_breaker_open' => 'Circuit breaker is open',
                default => $issue['type']
            };
            $issueDescriptions[] = $description;
        }

        return "Provider {$provider} health issues detected:\n".implode("\n", $issueDescriptions);
    }

    /**
     * Determine overall severity from issues
     */
    protected function determineSeverity(array $issues): string
    {
        $maxSeverity = 'low';

        $severityLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        foreach ($issues as $issue) {
            $issueSeverity = $issue['severity'] ?? 'medium';
            if ($severityLevels[$issueSeverity] > $severityLevels[$maxSeverity]) {
                $maxSeverity = $issueSeverity;
            }
        }

        return $maxSeverity;
    }

    /**
     * Send alerts through configured channels
     */
    protected function sendAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            $this->sendAlert($alert);
        }
    }

    /**
     * Send individual alert
     */
    protected function sendAlert(array $alert): void
    {
        // Log alert
        Log::log($this->getLogLevel($alert['severity']), '[AI Health Alert] '.$alert['title'], $alert);

        // Send notifications if configured
        if (in_array('notification', $this->alertChannels)) {
            $this->sendNotificationAlert($alert);
        }

        // Store alert for dashboard
        $this->storeAlert($alert);
    }

    /**
     * Send notification alert to administrators
     */
    protected function sendNotificationAlert(array $alert): void
    {
        try {
            $admins = User::where('is_admin', true)->get();

            if ($admins->isNotEmpty() && class_exists('\App\Notifications\AIServiceAlert')) {
                Notification::send($admins, new \App\Notifications\AIServiceAlert($alert));
            }

        } catch (Exception $e) {
            Log::error('[HealthMonitoringService] Failed to send notification alert', [
                'error' => $e->getMessage(),
                'alert_id' => $alert['id'],
            ]);
        }
    }

    /**
     * Store alert for dashboard display
     */
    protected function storeAlert(array $alert): void
    {
        $alertsKey = 'ai_health_alerts';
        $alerts = Cache::get($alertsKey, []);

        // Add new alert
        $alerts[] = $alert;

        // Keep only last 50 alerts
        $alerts = array_slice($alerts, -50);

        Cache::put($alertsKey, $alerts, now()->addDays(7));
    }

    /**
     * Get log level for severity
     */
    protected function getLogLevel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'info',
            default => 'info'
        };
    }

    /**
     * Get recent alerts
     */
    public function getRecentAlerts(int $hours = 24): array
    {
        $alerts = Cache::get('ai_health_alerts', []);
        $threshold = now()->subHours($hours)->timestamp;

        return array_filter($alerts, function ($alert) use ($threshold) {
            $alertTime = strtotime($alert['created_at']);

            return $alertTime > $threshold;
        });
    }

    /**
     * Clear old alerts
     */
    public function clearOldAlerts(int $days = 7): int
    {
        $alerts = Cache::get('ai_health_alerts', []);
        $threshold = now()->subDays($days)->timestamp;

        $filteredAlerts = array_filter($alerts, function ($alert) use ($threshold) {
            $alertTime = strtotime($alert['created_at']);

            return $alertTime > $threshold;
        });

        $removedCount = count($alerts) - count($filteredAlerts);

        Cache::put('ai_health_alerts', array_values($filteredAlerts), now()->addDays(7));

        return $removedCount;
    }
}
