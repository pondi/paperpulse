<?php

namespace App\Services\Jobs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PendingJobsReader
{
    public static function groupedByParent(): Collection
    {
        return DB::table('jobs')
            ->select(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (object $job): ?array {
                try {
                    $payload = json_decode($job->payload, true, 512, JSON_THROW_ON_ERROR);
                    if (! isset($payload['data']['command'])) {
                        Log::warning('Invalid job payload structure - missing command', compact('payload'));
                        return null;
                    }

                    $command = @unserialize($payload['data']['command']);
                    $jobID = JobCommandInspector::extractJobID($command);

                    $commandName = $payload['displayName']
                        ?? $payload['data']['commandName']
                        ?? ($command ? class_basename($command::class) : 'Unknown Job');

                    return [
                        'uuid' => $payload['uuid'] ?? Str::uuid()->toString(),
                        'parent_uuid' => $jobID,
                        'name' => $commandName,
                        'status' => $job->reserved_at ? 'processing' : 'pending',
                        'queue' => $job->queue,
                        'started_at' => TimestampFormatter::format($job->reserved_at ?? $job->created_at),
                        'progress' => 0,
                        'attempt' => $job->attempts,
                        'order_in_chain' => JobChainOrderResolver::resolve($commandName),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('Failed to process pending job', [
                        'error' => $e->getMessage(),
                        'job_id' => $job->id,
                        'payload' => $job->payload ?? null,
                    ]);
                    return null;
                }
            })
            ->filter()
            ->groupBy('parent_uuid');
    }
}

