<?php

namespace App\Providers;

use App\Jobs\BaseJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class JobServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Queue::createPayloadUsing(function (string $connection, ?string $queue, array $payload): array {
            $command = $payload['data']['command'] ?? null;
            $job = is_string($command) ? unserialize($command) : $command;
            if ($job instanceof BaseJob && $job->getUUID()) {
                return ['uuid' => $job->getUUID(), 'chain_id' => $job->getJobID()];
            }

            return [];
        });
    }
}
