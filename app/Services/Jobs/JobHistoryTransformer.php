<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;
use Illuminate\Support\Facades\Cache;

class JobHistoryTransformer
{
    public static function transform(JobHistory $job, bool $isChild = false): array
    {
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
            'duration' => self::calculateDuration($job),
            'order' => $job->order_in_chain,
        ];

        if (! $isChild) {
            $data['type'] = self::determineJobType($job);
            $data['file_info'] = self::resolveFileInfo($job);
            $data['steps'] = self::buildSteps($job);
        }

        return $data;
    }

    private static function calculateDuration(JobHistory $job): ?int
    {
        if ($job->started_at && $job->finished_at) {
            return abs($job->finished_at->diffInSeconds($job->started_at));
        }

        return null;
    }

    private static function resolveFileInfo(JobHistory $job): ?array
    {
        if ($job->file_name) {
            return [
                'name' => $job->file_name,
                'extension' => $job->metadata['fileExtension'] ?? pathinfo($job->file_name, PATHINFO_EXTENSION),
                'size' => $job->metadata['fileSize'] ?? null,
                'job_name' => $job->metadata['jobName'] ?? $job->name,
            ];
        }

        $metadata = Cache::get("job.{$job->uuid}.fileMetaData");
        if ($metadata) {
            return [
                'name' => $metadata['fileName'] ?? 'Unknown File',
                'extension' => $metadata['fileExtension'] ?? null,
                'size' => $metadata['fileSize'] ?? null,
                'job_name' => $metadata['jobName'] ?? 'Processing Job',
            ];
        }

        return null;
    }

    private static function determineJobType(JobHistory $job): string
    {
        $tasks = $job->tasks;

        if (! $tasks || $tasks->count() === 0) {
            return 'unknown';
        }

        if ($tasks->contains('name', 'Process Receipt')) {
            return 'receipt';
        }

        if ($tasks->contains('name', 'Process Document')) {
            return 'document';
        }

        return 'unknown';
    }

    private static function buildSteps(JobHistory $job): array
    {
        $children = $job->tasks()->orderBy('order_in_chain')->get();

        $uniqueSteps = [];
        foreach ($children->groupBy('name') as $attempts) {
            $latestAttempt = $attempts->sortByDesc('created_at')->first();
            $uniqueSteps[] = self::transform($latestAttempt, true);
        }

        return collect($uniqueSteps)->sortBy('order')->values()->all();
    }
}
