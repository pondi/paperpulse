<?php

namespace App\Jobs\Organization;

use App\Models\OrganizationRun;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GenerateOrganizationRecommendations implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 172800;

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
            Context::add('organization', ['run_id' => $run->id, 'user_id' => $run->user_id, 'cursor' => $run->cursor, 'attempt' => $run->attempts]);
            $planner->generate($run);
        } catch (Throwable $exception) {
            Context::add('organization', ['run_id' => $run->id, 'user_id' => $run->user_id, 'cursor' => $run->cursor, 'attempt' => $run->attempts]);
            $runs->fail($run);
            if ($exception instanceof ValidationException) {
                report(new RuntimeException('Folder planner returned invalid recommendations (run '.$run->id.', cursor '.$run->cursor.'): '.implode(' ', $exception->validator->errors()->all()), 0, $exception));
            }
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = OrganizationRun::withoutGlobalScope('user')->where('user_id', $this->userId)->find($this->runId);
        if ($run && $run->status === 'running') {
            app(OrganizationRunService::class)->fail($run);
        }
    }
}
