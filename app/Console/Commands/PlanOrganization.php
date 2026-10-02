<?php

namespace App\Console\Commands;

use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\OrganizationState;
use App\Services\OrganizationRunService;
use Illuminate\Console\Command;

class PlanOrganization extends Command
{
    protected $signature = 'organization:plan {--user= : Plan changes for one owner}';

    protected $description = 'Queue recommendations for changed archives and recover interrupted runs';

    public function handle(OrganizationRunService $runs): int
    {
        $states = OrganizationState::query()->whereColumn('revision', '>', 'analyzed_revision');
        if ($this->option('user')) {
            $states->where('user_id', $this->option('user'));
        }
        foreach ($states->lazyById(100) as $state) {
            $run = $runs->start($state->user_id, true);
            if (! $run || $run->wasRecentlyCreated) {
                continue;
            }
            if ($run->status === 'running' && $run->started_at->lt(now()->subMinutes(20))) {
                $runs->fail($run);
                $run->refresh();
            }
            if (in_array($run->status, ['queued', 'failed'], true) && $run->attempts < 3) {
                GenerateOrganizationRecommendations::dispatch($run->user_id, $run->id)->afterCommit();
            }
        }

        return self::SUCCESS;
    }
}
