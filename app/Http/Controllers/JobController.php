<?php

namespace App\Http\Controllers;

use App\Jobs\RestartJobChain;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Services\JobChainService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Transform a job history record into an array format.
     */
    private function transformJob(JobHistory $job, bool $isChild = false): array
    {
        // Calculate duration safely - ensure it's never negative
        $duration = null;
        if ($job->started_at && $job->finished_at) {
            $duration = abs($job->finished_at->diffInSeconds($job->started_at));
        }

        // Get file info for parent jobs
        $fileInfo = null;
        if (! $isChild) {
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

        // Determine job type from tasks
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

        $data = [
            'id' => $job->uuid,
            'name' => $job->name,
            'status' => $job->status,
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
            $data['steps'] = $job->tasks()
                ->orderBy('order_in_chain')
                ->get()
                ->map(fn (JobHistory $child): array => $this->transformJob($child, true))
                ->all();
        }

        return $data;
    }

    /**
     * Extract jobID from a command using reflection.
     */
    private function extractJobIDFromCommand(mixed $command): ?string
    {
        if (! $command) {
            return null;
        }

        try {
            if (method_exists($command, 'getJobID')) {
                return $command->getJobID();
            }

            $reflection = new \ReflectionClass($command);
            $property = $reflection->getProperty('jobID');
            $property->setAccessible(true);

            return $property->getValue($command);
        } catch (\Throwable $e) {
            Log::warning('Failed to extract jobID from command', [
                'command_class' => $command::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get pending jobs from the queue.
     */
    private function getPendingJobs(): Collection
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
                    $jobID = $this->extractJobIDFromCommand($command);

                    $commandName = $payload['displayName']
                        ?? $payload['data']['commandName']
                        ?? ($command ? class_basename($command::class) : 'Unknown Job');

                    return [
                        'uuid' => $payload['uuid'] ?? Str::uuid()->toString(),
                        'parent_uuid' => $jobID,
                        'name' => $commandName,
                        'status' => $job->reserved_at ? 'processing' : 'pending',
                        'queue' => $job->queue,
                        'started_at' => $this->formatTimestamp($job->reserved_at ?? $job->created_at),
                        'progress' => 0,
                        'attempt' => $job->attempts,
                        'order_in_chain' => $this->getOrderInChain($commandName),
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

    /**
     * Format a timestamp to a consistent datetime string.
     */
    private function formatTimestamp(string|int|null $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return is_int($timestamp)
            ? Carbon::createFromTimestamp($timestamp)->toIso8601String()
            : Carbon::parse($timestamp)->toIso8601String();
    }

    /**
     * Get job statistics.
     */
    private function getJobStats(): array
    {
        $query = JobHistory::parentJobs();

        return [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => $query->where('status', 'failed')->count(),
        ];
    }

    /**
     * Get PulseDav file processing statistics.
     */
    private function getPulseDavStats(): array
    {
        $query = PulseDavFile::where('user_id', auth()->id());

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }

    /**
     * Display the jobs index page.
     */
    public function index(Request $request): Response
    {
        $historyQuery = JobHistory::query()
            ->whereNull('parent_uuid')
            ->orderBy('created_at', 'desc')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('queue'), fn (Builder $query) => $query->where('queue', $request->queue))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', "%{$request->search}%"));

        $historyJobs = $historyQuery->with('tasks')->paginate(50);

        // Get recent PulseDav files with processing status
        $recentPulseDavFiles = PulseDavFile::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'status' => $file->status,
                    'uploaded_at' => $file->uploaded_at?->toIso8601String(),
                    'processed_at' => $file->processed_at?->toIso8601String(),
                    'error_message' => $file->error_message,
                    'receipt_id' => $file->receipt_id,
                ];
            });

        return Inertia::render('Jobs/Index', [
            'jobs' => $historyJobs->map(fn (JobHistory $job) => $this->transformJob($job))->all(),
            'stats' => $this->getJobStats(),
            'pulseDavStats' => $this->getPulseDavStats(),
            'recentPulseDavFiles' => $recentPulseDavFiles,
            'queues' => JobHistory::select('queue')
                ->whereNull('parent_uuid')
                ->distinct()
                ->pluck('queue')
                ->all(),
            'filters' => [
                'status' => $request->input('status', ''),
                'queue' => $request->input('queue', ''),
                'search' => $request->input('search', ''),
            ],
            'pagination' => [
                'current_page' => $historyJobs->currentPage(),
                'last_page' => $historyJobs->lastPage(),
                'per_page' => $historyJobs->perPage(),
                'total' => $historyJobs->total(),
            ],
        ]);
    }

    /**
     * Get the list of jobs with their status and progress.
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $pendingJobs = $this->getPendingJobs();

            $historyQuery = JobHistory::parentJobs()
                ->when($request->status, fn (Builder $query) => $query->where('status', $request->status))
                ->when($request->queue, fn (Builder $query) => $query->where('queue', $request->queue))
                ->when($request->search, fn (Builder $query) => $query->where('name', 'like', "%{$request->search}%"));

            $historyJobs = $historyQuery
                ->with('tasks')
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json([
                'data' => $historyJobs->map(function (JobHistory $job) use ($pendingJobs): array {
                    $jobData = $this->transformJob($job);

                    if (isset($pendingJobs[$job->uuid])) {
                        $pendingTasks = $pendingJobs[$job->uuid]->values();
                        $jobData['steps'] = collect($jobData['steps'])
                            ->concat($pendingTasks)
                            ->sortBy('order')
                            ->values()
                            ->all();
                    }

                    return $jobData;
                }),
                'stats' => $this->getJobStats(),
                'queues' => JobHistory::select('queue')->distinct()->pluck('queue'),
                'pagination' => [
                    'total' => $historyJobs->total(),
                    'per_page' => $historyJobs->perPage(),
                    'current_page' => $historyJobs->currentPage(),
                    'last_page' => $historyJobs->lastPage(),
                ],
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch job status:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Could not fetch job status',
                'error' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get the order in the job chain for a given job type.
     */
    private function getOrderInChain(string $jobName): int
    {
        return \App\Jobs\JobOrder::getOrder($jobName);
    }

    /**
     * Restart a failed job chain
     */
    public function restart(Request $request, string $jobId): JsonResponse
    {
        try {
            // Validate that the job exists and can be restarted
            $jobChainService = app(JobChainService::class);
            $status = $jobChainService->getJobChainStatus($jobId);

            if (! $status['found']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job chain not found',
                ], 404);
            }

            if (! $status['can_restart']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job chain cannot be restarted - no failed tasks found',
                ], 400);
            }

            // Generate a new job ID for the restart operation
            $restartJobId = (string) Str::uuid();

            // Dispatch the restart job
            Bus::dispatch(new RestartJobChain($restartJobId, $jobId));

            Log::info('Job chain restart requested', [
                'original_job_id' => $jobId,
                'restart_job_id' => $restartJobId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job chain restart initiated',
                'restart_job_id' => $restartJobId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restart job chain', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restart job chain: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restart multiple job chains
     */
    public function restartMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'job_ids' => 'required|array|min:1|max:10',
            'job_ids.*' => 'required|string|uuid',
        ]);

        try {
            $jobChainService = app(JobChainService::class);
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($request->job_ids as $jobId) {
                try {
                    // Check if job can be restarted
                    $status = $jobChainService->getJobChainStatus($jobId);

                    if (! $status['found']) {
                        $results[$jobId] = [
                            'success' => false,
                            'message' => 'Job chain not found',
                        ];
                        $failureCount++;

                        continue;
                    }

                    if (! $status['can_restart']) {
                        $results[$jobId] = [
                            'success' => false,
                            'message' => 'Job chain cannot be restarted - no failed tasks found',
                        ];
                        $failureCount++;

                        continue;
                    }

                    // Generate a new job ID for the restart operation
                    $restartJobId = (string) Str::uuid();

                    // Dispatch the restart job
                    Bus::dispatch(new RestartJobChain($restartJobId, $jobId));

                    $results[$jobId] = [
                        'success' => true,
                        'message' => 'Job chain restart initiated',
                        'restart_job_id' => $restartJobId,
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $results[$jobId] = [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                    $failureCount++;
                }
            }

            Log::info('Bulk job chain restart requested', [
                'job_ids' => $request->job_ids,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => $failureCount === 0,
                'message' => "Restarted {$successCount} job chains, {$failureCount} failed",
                'results' => $results,
                'summary' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'total_count' => count($request->job_ids),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restart multiple job chains', [
                'job_ids' => $request->job_ids ?? [],
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restart job chains: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed status for a specific job chain
     */
    public function show(string $jobId): JsonResponse
    {
        try {
            $jobChainService = app(JobChainService::class);
            $status = $jobChainService->getJobChainStatus($jobId);

            if (! $status['found']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job chain not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get job chain status', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get job chain status: '.$e->getMessage(),
            ], 500);
        }
    }
}
