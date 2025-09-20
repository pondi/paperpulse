<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;
use Illuminate\Support\Facades\Cache;

class JobHistoryTransformer
{
    public static function transform(JobHistory $job, bool $isChild = false): array
    {
        $duration = null;
        if ($job->started_at && $job->finished_at) {
            $duration = abs($job->finished_at->diffInSeconds($job->started_at));
        }

        $fileInfo = null;
        if (! $isChild) {
            if ($job->file_name) {
                $fileInfo = [
                    'name' => $job->file_name,
                    'extension' => $job->metadata['fileExtension'] ?? pathinfo($job->file_name, PATHINFO_EXTENSION),
                    'size' => $job->metadata['fileSize'] ?? null,
                    'job_name' => $job->metadata['jobName'] ?? $job->name,
                ];
            } else {
                $metadata = Cache::get("job.{$job->uuid}.fileMetaData");
                if ($metadata) {
                    $fileInfo = [
                        'name' => $metadata['fileName'] ?? 'Unknown File',
                        'extension' => $metadata['fileExtension'] ?? null,
                        'size' => $metadata['fileSize'] ?? null,
                        'job_name' => $metadata['jobName'] ?? 'Processing Job',
                    ];
                }
            }
        }

        $jobType = 'unknown';
        if (! $isChild) {
            $tasks = $job->tasks;
            if ($tasks && $tasks->count() > 0) {
                if ($tasks->contains('name', 'Process Receipt')) {
                    $jobType = 'receipt';
                } elseif ($tasks->contains('name', 'Process Document')) {
                    $jobType = 'document';
                }
            }
        }

        $effectiveStatus = $isChild ? $job->status : JobParentStatusCalculator::calculate($job);

        $data = [
            'id' => $job->uuid,
            'name' => $job->name,
            'status' => $effectiveStatus,
            'queue' => $job->queue,
            'started_at' => $job->started_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'progress' => (int) $job->progress,
            'attempt' => $job->attempt,
            'exception' => $job->exception,
            'duration' => $duration,
            'order' => $job->order_in_chain,
        ];

        if (! $isChild) {
            $data['type'] = $jobType;
            $data['file_info'] = $fileInfo;

            $children = $job->tasks()->orderBy('order_in_chain')->get();
            $uniqueSteps = [];
            foreach ($children->groupBy('name') as $attempts) {
                $latestAttempt = $attempts->sortByDesc('created_at')->first();
                $uniqueSteps[] = self::transform($latestAttempt, true);
            }
            $data['steps'] = collect($uniqueSteps)->sortBy('order')->values()->all();
        }

        return $data;
    }
}
