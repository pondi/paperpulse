<?php

namespace App\Http\Controllers;

use App\Models\JobHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Bus;

class JobController extends Controller
{
    public function index()
    {
        try {
            // Get job history with relationships
            $jobHistories = JobHistory::latest()
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->job_uuid,
                        'name' => class_basename($job->name),
                        'status' => $job->status,
                        'queue' => $job->queue,
                        'started_at' => $job->started_at,
                        'finished_at' => $job->finished_at,
                        'progress' => $this->calculateProgress($job),
                        'attempts' => $job->attempts,
                        'exception' => $job->exception,
                        'jobId' => $job->job_id,
                        'chain' => $job->payload['chain'] ?? [],
                    ];
                });

            // Group jobs by chain ID
            $jobChains = $jobHistories
                ->filter(fn($job) => !empty($job['jobId']))
                ->groupBy('jobId')
                ->map(function ($jobs) {
                    return [
                        'jobs' => $jobs->values(),
                        'total' => $jobs->count(),
                        'completed' => $jobs->where('status', 'completed')->count(),
                        'failed' => $jobs->where('status', 'failed')->count(),
                        'progress' => $this->calculateChainProgress($jobs),
                    ];
                });

            // Get queue sizes using Laravel's Queue facade
            $queues = config('queue.queues', ['default']);
            $queueSizes = collect($queues)->mapWithKeys(function ($queue) {
                return [$queue => Queue::size($queue)];
            });

            return response()->json([
                'data' => $jobHistories->values(),
                'chains' => $jobChains,
                'queued' => $queueSizes,
                'counts' => [
                    'pending' => $jobHistories->where('status', 'queued')->count(),
                    'processing' => $jobHistories->where('status', 'processing')->count(),
                    'failed' => $jobHistories->where('status', 'failed')->count(),
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'Could not fetch job status',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function rerun(string $jobUuid)
    {
        try {
            $jobHistory = JobHistory::where('job_uuid', $jobUuid)
                ->where('status', 'failed')
                ->firstOrFail();

            $jobClass = $jobHistory->name;
            
            if (!class_exists($jobClass)) {
                throw new \Exception("Job class {$jobClass} not found");
            }

            // Create and dispatch a new instance of the job
            $job = new $jobClass($jobHistory->job_id);
            
            // If this was part of a chain, recreate the chain
            if (!empty($jobHistory->payload['chain'])) {
                $chain = collect($jobHistory->payload['chain'])
                    ->map(function ($serializedJob) use ($jobHistory) {
                        $jobData = unserialize($serializedJob);
                        return new $jobData::class($jobHistory->job_id);
                    })
                    ->toArray();
                
                Bus::chain([$job, ...$chain])->dispatch();
            } else {
                dispatch($job);
            }

            return response()->json([
                'message' => 'Job requeued successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'Failed to rerun job',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    protected function calculateProgress(JobHistory $job): int
    {
        return match($job->status) {
            'completed' => 100,
            'processing' => 50,
            'failed' => $job->attempts > 0 ? 25 : 0,
            default => 0
        };
    }

    protected function calculateChainProgress($jobs): float
    {
        $total = $jobs->count();
        if ($total === 0) return 0;

        $completed = $jobs->where('status', 'completed')->count();
        $processing = $jobs->where('status', 'processing')->count();
        
        return round(($completed + ($processing * 0.5)) * (100 / $total), 1);
    }
}