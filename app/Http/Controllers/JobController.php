<?php

namespace App\Http\Controllers;

use App\Jobs\System\RestartJobChain;
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
        return \App\Services\Jobs\JobHistoryTransformer::transform($job, $isChild);
    }

    /**
     * Extract jobID from a command using reflection.
     */
    private function extractJobIDFromCommand(mixed $command): ?string
    {
        return \App\Services\Jobs\JobCommandInspector::extractJobID($command);
    }

    /**
     * Get pending jobs from the queue.
     */
    private function getPendingJobs(): Collection
    {
        return \App\Services\Jobs\PendingJobsReader::groupedByParent();
    }

    /**
     * Format a timestamp to a consistent datetime string.
     */
    private function formatTimestamp(string|int|null $timestamp): ?string
    {
        return \App\Services\Jobs\TimestampFormatter::format($timestamp);
    }

    /**
     * Calculate the effective status of a parent job based on its children.
     */
    private function calculateParentJobStatus(JobHistory $parentJob): string
    {
        return \App\Services\Jobs\JobParentStatusCalculator::calculate($parentJob);
    }

    /**
     * Get job statistics.
     */
    private function getJobStats(): array
    {
        return \App\Services\Jobs\JobStatisticsProvider::overall();
    }

    /**
     * Get PulseDav file processing statistics.
     */
    private function getPulseDavStats(): array
    {
        return \App\Services\Jobs\PulseDavStatisticsProvider::forUser(auth()->id());
    }

    /**
     * Display the jobs index page.
     */
    public function index(Request $request): Response
    {
        // Restrict job visibility to administrators
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized');
        }
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
        if (! auth()->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
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
                'queues' => JobHistory::whereNull('parent_uuid')->select('queue')->distinct()->pluck('queue'),
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
        return \App\Services\Jobs\JobChainOrderResolver::resolve($jobName);
    }

    /**
     * Restart a failed job chain
     */
    public function restart(Request $request, string $jobId)
    {
        if (! auth()->user()?->isAdmin()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized');
        }
        
        try {
            // Validate that the job exists and can be restarted
            $jobChainService = app(JobChainService::class);
            $status = $jobChainService->getJobChainStatus($jobId);

            if (! $status['found']) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job chain not found',
                    ], 404);
                }
                return back()->with('error', 'Job chain not found');
            }

            if (! $status['can_restart']) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job chain cannot be restarted - no failed tasks found',
                    ], 400);
                }
                return back()->with('error', 'Job chain cannot be restarted - no failed tasks found');
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

            // For Inertia requests, redirect back with success message
            if ($request->header('X-Inertia')) {
                return redirect()->route('jobs.index')->with('success', 'Job chain restart initiated');
            }

            // For API requests, return JSON
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Job chain restart initiated',
                    'restart_job_id' => $restartJobId,
                ]);
            }

            // Default redirect for web requests
            return redirect()->route('jobs.index')->with('success', 'Job chain restart initiated');

        } catch (\Exception $e) {
            Log::error('Failed to restart job chain', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restart job chain: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to restart job chain: '.$e->getMessage());
        }
    }

    /**
     * Restart multiple job chains
     */
    public function restartMultiple(Request $request): JsonResponse
    {
        if (! auth()->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
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
        if (! auth()->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
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
