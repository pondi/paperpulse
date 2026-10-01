<?php

namespace App\Services\Jobs;

use App\Jobs\BaseJob;
use App\Models\JobHistory;
use RuntimeException;

class JobChainPlan
{
    public static function prependStep(string $chainId, string $currentStepUuid, BaseJob $step): void
    {
        (new JobHistory)->getConnection()->transaction(function () use ($chainId, $currentStepUuid, $step): void {
            $parent = JobHistory::query()->where('uuid', $chainId)->lockForUpdate()->firstOrFail();
            $metadata = $parent->metadata ?? [];
            $plan = $metadata['plannedSteps'] ?? [];
            if (collect($plan)->contains('uuid', $step->uuid)) {
                return;
            }
            $currentIndex = collect($plan)->search(fn (array $planned): bool => $planned['uuid'] === $currentStepUuid);
            if ($currentIndex === false || $step->jobID !== $chainId) {
                throw new RuntimeException('Cannot prepend a step to a missing or foreign chain plan.');
            }
            array_splice($plan, $currentIndex + 1, 0, [['uuid' => $step->uuid, 'class' => $step::class, 'required' => true]]);
            foreach ($plan as $index => &$planned) {
                $planned['order'] = $index + 1;
                JobHistory::query()->updateOrCreate(['uuid' => $planned['uuid']], [
                    'parent_uuid' => $chainId,
                    'name' => class_basename($planned['class']),
                    'queue' => $step->queue ?? $parent->queue,
                    'order_in_chain' => $index + 1,
                ]);
                JobHistory::query()->where('uuid', $planned['uuid'])->whereNull('status')->update(['status' => 'pending']);
            }
            unset($planned);
            $metadata['plannedSteps'] = $plan;
            JobMetadataPersistence::store($chainId, $metadata);
        });
    }
}
