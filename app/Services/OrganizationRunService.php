<?php

namespace App\Services;

use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\OrganizationBackfill;
use App\Models\OrganizationRun;
use App\Models\User;
use App\Models\UserPreference;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class OrganizationRunService
{
    public function __construct(private OrganizationRevisionService $revisions) {}

    public function start(int $userId, bool $scheduled = false): ?OrganizationRun
    {
        return (new OrganizationRun)->getConnection()->transaction(function () use ($userId, $scheduled): ?OrganizationRun {
            User::query()->lockForUpdate()->findOrFail($userId);
            if (UserPreference::query()->where('user_id', $userId)->value('auto_organize_documents') === false) {
                return null;
            }
            $active = OrganizationRun::withoutGlobalScope('user')->where('active_user_id', $userId)->first();
            if ($active) {
                return $active;
            }
            if (OrganizationBackfill::withoutGlobalScope('user')->where('active_user_id', $userId)->exists()) {
                return null;
            }
            $state = $this->revisions->state($userId);
            if (! $this->revisions->hasChanges($userId) || ($scheduled && $state->debounce_until?->isFuture())) {
                return null;
            }
            $snapshot = $this->revisions->snapshot($userId);
            if ($snapshot['fingerprint'] === $state->analyzed_fingerprint) {
                $this->revisions->markAnalyzed($userId, $snapshot['revision'], $snapshot['fingerprint']);

                return null;
            }

            $run = OrganizationRun::query()->create(['user_id' => $userId, 'active_user_id' => $userId,
                'input_revision' => $snapshot['revision'], 'input_fingerprint' => $snapshot['fingerprint']]);
            GenerateOrganizationRecommendations::dispatch($userId, $run->id)->afterCommit();

            return $run;
        });
    }

    public function begin(int $userId, int $runId): ?OrganizationRun
    {
        return (new OrganizationRun)->getConnection()->transaction(function () use ($userId, $runId): ?OrganizationRun {
            User::query()->lockForUpdate()->findOrFail($userId);
            $run = OrganizationRun::withoutGlobalScope('user')->where('user_id', $userId)->lockForUpdate()->findOrFail($runId);
            if (! in_array($run->status, ['queued', 'failed'], true) || $run->attempts >= 3) {
                return null;
            }
            $run->update(['status' => 'running', 'attempts' => $run->attempts + 1, 'error' => null, 'started_at' => now()]);

            return $run;
        });
    }

    public function finish(OrganizationRun $run): void
    {
        $run->getConnection()->transaction(function () use ($run): void {
            User::query()->lockForUpdate()->findOrFail($run->user_id);
            $locked = OrganizationRun::withoutGlobalScope('user')->where('user_id', $run->user_id)->lockForUpdate()->findOrFail($run->id);
            if ($locked->status === 'completed') {
                return;
            }
            if ($locked->recommendations()->withoutGlobalScope('user')->whereIn('status', ['pending', 'conflict'])->exists()) {
                $locked->update(['status' => 'awaiting_decisions']);

                return;
            }
            $locked->update(['status' => 'completed', 'active_user_id' => null, 'completed_at' => now()]);
            $this->revisions->markAnalyzed($locked->user_id, $locked->input_revision, $locked->input_fingerprint);
        });
    }

    public function continue(OrganizationRun $run, ?DateTimeInterface $delay = null): void
    {
        $run->getConnection()->transaction(function () use ($run, $delay): void {
            User::query()->lockForUpdate()->findOrFail($run->user_id);
            $run->update(['status' => 'queued', 'attempts' => 0, 'error' => null]);
            GenerateOrganizationRecommendations::dispatch($run->user_id, $run->id)->delay($delay)->afterCommit();
        });
    }

    public function fail(OrganizationRun $run): void
    {
        OrganizationRun::withoutGlobalScope('user')->where('user_id', $run->user_id)->whereKey($run->id)
            ->where('status', 'running')->update(['status' => 'failed', 'error' => 'Recommendations could not be generated. Retry this run or dismiss it.']);
    }

    public function retry(int $userId, int $runId): OrganizationRun
    {
        return (new OrganizationRun)->getConnection()->transaction(function () use ($userId, $runId): OrganizationRun {
            User::query()->lockForUpdate()->findOrFail($userId);
            $run = OrganizationRun::withoutGlobalScope('user')->where('user_id', $userId)->lockForUpdate()->findOrFail($runId);
            if ($run->status !== 'failed' || $run->attempts >= 3) {
                throw ValidationException::withMessages(['organization' => 'This run cannot be retried.']);
            }
            $run->update(['status' => 'queued', 'error' => null]);

            return $run;
        });
    }
}
