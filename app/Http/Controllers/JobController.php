<?php

namespace App\Http\Controllers;

use App\Models\JobHistory;
use App\Models\PulseDavFile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $data = [
            'id' => $job->uuid,
            'name' => $job->name,
            'status' => $job->status,
            'queue' => $job->queue,
            'started_at' => $job->started_at?->toDateTimeString(),
            'finished_at' => $job->finished_at?->toDateTimeString(),
            'progress' => (int) $job->progress,
            'attempt' => $job->attempt,
            'exception' => $job->exception,
            'duration' => $job->finished_at?->diffInSeconds($job->started_at),
            'order' => $job->order_in_chain,
        ];

        if (! $isChild) {
            $data['tasks'] = $job->tasks()
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
            ? Carbon::createFromTimestamp($timestamp)->toDateTimeString()
            : $timestamp;
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
                    'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                    'processed_at' => $file->processed_at?->toDateTimeString(),
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
                        $jobData['tasks'] = collect($jobData['tasks'])
                            ->concat($pendingTasks)
                            ->sortBy('order_in_chain')
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
        return match (class_basename($jobName)) {
            'ProcessFile' => 1,
            'ProcessReceipt' => 2,
            'MatchMerchant' => 3,
            'DeleteWorkingFiles' => 4,
            default => 0,
        };
    }
}
