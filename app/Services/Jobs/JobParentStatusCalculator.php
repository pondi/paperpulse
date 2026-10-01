<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;

class JobParentStatusCalculator
{
    public static function calculate(JobHistory $parentJob): string
    {
        $children = $parentJob->tasks()->get()->keyBy('uuid');
        $plan = $parentJob->metadata['plannedSteps'] ?? [];
        $statuses = $plan
            ? collect($plan)->filter(fn (array $step): bool => $step['required'] ?? true)
                ->map(fn (array $step): string => $children->get($step['uuid'])?->status ?? 'pending')
            : $children->pluck('status');
        if ($statuses->isEmpty()) {
            return $parentJob->status;
        }
        $allTerminal = $statuses->every(fn (string $status): bool => in_array($status, ['completed', 'failed', 'cancelled'], true));
        if ($allTerminal) {
            return $statuses->contains(fn (string $status): bool => $status !== 'completed') ? 'failed' : 'completed';
        }
        if ($statuses->contains(fn (string $status): bool => in_array($status, ['processing', 'retrying', 'completed', 'failed'], true))) {
            return 'processing';
        }

        return 'pending';
    }

    public static function update(string $parentUuid): void
    {
        $parent = JobHistory::query()->where('uuid', $parentUuid)->first();
        if (! $parent) {
            return;
        }
        $children = $parent->tasks()->get()->keyBy('uuid');
        $plan = $parent->metadata['plannedSteps'] ?? [];
        $requiredIds = $plan
            ? collect($plan)->filter(fn (array $step): bool => $step['required'] ?? true)->pluck('uuid')
            : $children->keys();
        $progress = $requiredIds->isEmpty() ? 0 : (int) round($requiredIds->sum(fn (string $uuid): int => (int) ($children->get($uuid)?->progress ?? 0)) / $requiredIds->count());
        $status = self::calculate($parent);
        $parent->update([
            'status' => $status,
            'progress' => $progress,
            'finished_at' => in_array($status, ['completed', 'failed'], true) ? now() : null,
        ]);
    }
}
