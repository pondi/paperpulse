<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\File;
use App\Models\JobHistory;
use Illuminate\Http\JsonResponse;

class JobController extends BaseApiController
{
    public function show(string $jobId): JsonResponse
    {
        $job = JobHistory::query()
            ->where('uuid', $jobId)
            ->whereNull('parent_uuid')
            ->first();

        if (! $job) {
            return $this->notFound('Job not found');
        }

        // Scope to authenticated user via file_id.
        // file_id is required — jobs without a file cannot be authorized.
        if (! $job->file_id) {
            return $this->notFound('Job not found');
        }

        $file = File::find($job->file_id);
        if (! $file) {
            return $this->notFound('Job not found');
        }

        $job->load('tasks');

        return $this->success([
            'id' => $job->uuid,
            'name' => $job->name,
            'status' => $job->status,
            'progress' => $job->progress,
            'current_step' => $this->getCurrentStep($job),
            'file_id' => $job->file_id,
            'started_at' => $job->started_at?->toISOString(),
            'completed_at' => $job->finished_at?->toISOString(),
            'error' => $job->exception,
            'tasks' => $job->tasks->map(fn (JobHistory $task) => [
                'name' => $task->name,
                'status' => $task->status,
                'progress' => $task->progress,
                'started_at' => $task->started_at?->toISOString(),
                'completed_at' => $task->finished_at?->toISOString(),
            ])->values(),
        ], 'Job status retrieved');
    }

    private function getCurrentStep(JobHistory $job): ?string
    {
        if (! $job->relationLoaded('tasks')) {
            return null;
        }

        $processing = $job->tasks->firstWhere('status', 'processing');

        return $processing?->name;
    }
}
