<?php

namespace App\Jobs\Organization;

use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateOrganizationRecommendations implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 1200;

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->runId;
    }

    public function __construct(public int $userId, public int $runId)
    {
        $this->onConnection('database')->onQueue('default');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(OrganizationRunService $runs, OrganizationPlanner $planner): void
    {
        $run = $runs->begin($this->userId, $this->runId);
        if (! $run) {
            return;
        }
        try {
            $planner->generate($run);
        } catch (Throwable $exception) {
            $runs->fail($run);
            throw $exception;
        }
    }
}
