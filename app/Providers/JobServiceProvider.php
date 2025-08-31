<?php

namespace App\Providers;

use App\Models\JobHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class JobServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    protected function extractJobIDFromCommand($command): ?string
    {
        if (method_exists($command, 'getJobID')) {
            return $command->getJobID();
        }

        // Try to access protected property through reflection
        try {
            $reflection = new \ReflectionClass($command);
            $property = $reflection->getProperty('jobID');
            $property->setAccessible(true);

            return $property->getValue($command);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getJobDetails($command, $payload): array
    {
        try {
            $uuid = $payload['uuid'] ?? null;
            $commandName = $payload['data']['commandName'] ?? null;

            // Try to get jobID from various sources
            $jobID = $this->extractJobIDFromCommand($command);

            // If jobID is still null, try to get it from the serialized command
            if (! $jobID && isset($payload['data']['command'])) {
                $commandData = $payload['data']['command'];
                if (is_string($commandData)) {
                    $unserialized = @unserialize($commandData);
                    if ($unserialized) {
                        $jobID = $this->extractJobIDFromCommand($unserialized);
                    }
                }
            }

            // Base return array with all required fields
            $baseReturn = [
                'name' => $commandName ?? class_basename($command),
                'parent_uuid' => null,
                'metadata' => null,
                'uuid' => $uuid,
                'order_in_chain' => null,
                'queue' => 'receipts',
                'command_data' => $payload['data'] ?? [],
            ];

            if (! $jobID || ! $uuid || ! $commandName) {
                Log::error('Missing required job information', [
                    'jobID' => $jobID,
                    'uuid' => $uuid,
                    'commandName' => $commandName,
                    'command_class' => get_class($command),
                    'payload' => $payload,
                    'command_data' => $payload['data']['command'] ?? null,
                    'unserialized_command' => isset($unserialized) ? get_class($unserialized) : null,
                ]);

                return $baseReturn;
            }

            // Get metadata from cache
            $metadata = Cache::get("job.{$jobID}.fileMetaData");

            // Set order in chain based on job type using centralized helper
            $orderInChain = \App\Jobs\JobOrder::getOrder($commandName ?? class_basename($command));

            return [
                'name' => $commandName,
                'parent_uuid' => $jobID,
                'metadata' => $metadata,
                'order_in_chain' => $orderInChain,
                'uuid' => $uuid,
                'queue' => $payload['queue'] ?? 'receipts',
                'command_data' => $payload['data'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get job details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
                'command_class' => get_class($command ?? null),
                'command_data' => $payload['data']['command'] ?? null,
                'unserialized_command' => isset($unserialized) ? get_class($unserialized) : null,
            ]);

            return [
                'name' => $payload['data']['commandName'] ?? class_basename($command),
                'parent_uuid' => null,
                'metadata' => null,
                'uuid' => $uuid ?? null,
                'order_in_chain' => null,
                'queue' => 'receipts',
                'command_data' => $payload['data'] ?? [],
            ];
        }
    }

    public function boot(): void
    {
        // Before processing: Create history entry when job starts
        Queue::before(function ($event) {
            try {
                $payload = $event->job->payload();
                $command = null;

                // Try to unserialize command
                if (isset($payload['data']['command'])) {
                    $command = @unserialize($payload['data']['command']);
                }

                if (! $command) {
                    Log::error('Failed to unserialize command', [
                        'payload' => $payload,
                        'raw_command' => $payload['data']['command'] ?? null,
                    ]);

                    return;
                }

                $details = $this->getJobDetails($command, $payload);

                // Ensure all required fields are present
                $details = array_merge([
                    'uuid' => null,
                    'name' => null,
                    'parent_uuid' => null,
                    'metadata' => null,
                    'queue' => 'receipts',
                    'order_in_chain' => null,
                    'command_data' => [],
                ], $details);

                if (! $details['uuid'] || ! $details['name']) {
                    Log::error('Missing required job details', [
                        'details' => $details,
                        'command_class' => get_class($command),
                        'command_data' => $payload['data']['command'] ?? null,
                    ]);

                    return;
                }

                // For the first job in chain (ProcessFile or ProcessPulseDavFile), create or update parent job
                if (in_array(class_basename($command), ['ProcessFile', 'ProcessPulseDavFile'])) {
                    // For ProcessPulseDavFile, it IS the parent job
                    if (class_basename($command) === 'ProcessPulseDavFile') {
                        JobHistory::updateOrCreate(
                            ['uuid' => $details['uuid']], // Use its own UUID
                            [
                                'parent_uuid' => null,
                                'name' => $details['name'],
                                'queue' => $details['queue'],
                                'payload' => [
                                    'metadata' => $details['metadata'],
                                    'status' => 'processing',
                                    'command' => $details['name'],
                                    'data' => $details['command_data'],
                                ],
                                'status' => 'processing',
                                'started_at' => now(),
                                'progress' => 0,
                                'order_in_chain' => null,
                                'attempt' => $event->job->attempts(),
                                'exception' => null,
                                'finished_at' => null,
                            ]
                        );
                    } else {
                        // For ProcessFile, create parent if it doesn't exist
                        JobHistory::updateOrCreate(
                            ['uuid' => $details['parent_uuid']],
                            [
                                'parent_uuid' => null,
                                'name' => $details['metadata']['jobName'] ?? 'Document Processing',
                                'queue' => $details['queue'],
                                'payload' => [
                                    'metadata' => $details['metadata'],
                                    'status' => 'processing',
                                    'command' => $details['name'],
                                    'data' => $details['command_data'],
                                ],
                                'status' => 'processing',
                                'started_at' => now(),
                                'progress' => 0,
                                'order_in_chain' => null,
                                'attempt' => $event->job->attempts(),
                                'exception' => null,
                                'finished_at' => null,
                            ]
                        );
                    }
                }

                // Create or update task entry
                JobHistory::updateOrCreate(
                    ['uuid' => $details['uuid']],
                    [
                        'parent_uuid' => $details['parent_uuid'],
                        'name' => $details['name'],
                        'queue' => $details['queue'],
                        'payload' => [
                            'metadata' => $details['metadata'],
                            'command' => $details['name'],
                            'data' => $details['command_data'],
                        ],
                        'status' => 'processing',
                        'attempt' => $event->job->attempts(),
                        'started_at' => now(),
                        'progress' => 0,
                        'order_in_chain' => $details['order_in_chain'],
                        'exception' => null,
                        'finished_at' => null,
                    ]
                );

                // Update parent progress
                if ($details['parent_uuid']) {
                    $this->updateParentProgress($details['parent_uuid']);
                }

                Log::info('Job task started', [
                    'uuid' => $details['uuid'],
                    'name' => $details['name'],
                    'parent_uuid' => $details['parent_uuid'],
                    'order' => $details['order_in_chain'],
                    'attempt' => $event->job->attempts(),
                    'command_class' => get_class($command),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to process job start', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'payload' => $payload ?? null,
                    'command_data' => $payload['data']['command'] ?? null,
                ]);
            }
        });

        // After processing: Update history when job completes
        Queue::after(function ($event) {
            try {
                $payload = $event->job->payload();
                if (! isset($payload['uuid'])) {
                    Log::error('Missing UUID in job payload');

                    return;
                }

                $jobHistory = JobHistory::where('uuid', $payload['uuid'])->first();

                if ($jobHistory) {
                    $jobHistory->update([
                        'status' => 'completed',
                        'finished_at' => now(),
                        'progress' => 100,
                    ]);

                    // Update parent progress
                    if ($jobHistory->parent_uuid) {
                        $this->updateParentProgress($jobHistory->parent_uuid);
                    }

                    Log::info('Job completed', [
                        'uuid' => $payload['uuid'],
                        'parent_uuid' => $jobHistory->parent_uuid,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process job completion', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // On failure: Update history when job fails
        Queue::failing(function ($event) {
            try {
                $payload = $event->job->payload();
                if (! isset($payload['uuid'])) {
                    Log::error('Missing UUID in job payload on failure');

                    return;
                }

                $jobHistory = JobHistory::where('uuid', $payload['uuid'])->first();

                if ($jobHistory) {
                    $jobHistory->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'exception' => $event->exception->getMessage(),
                    ]);

                    // Mark parent as failed
                    if ($jobHistory->parent_uuid) {
                        JobHistory::where('uuid', $jobHistory->parent_uuid)
                            ->whereNull('parent_uuid')
                            ->update([
                                'status' => 'failed',
                                'finished_at' => now(),
                                'exception' => 'Chain failed: '.$event->exception->getMessage(),
                            ]);
                    }

                    Log::error('Job failed', [
                        'uuid' => $payload['uuid'],
                        'exception' => $event->exception->getMessage(),
                        'parent_uuid' => $jobHistory->parent_uuid,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process job failure', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // Before worker gets next job
        Queue::looping(function () {
            try {
                // Clean up stuck jobs
                JobHistory::where('status', 'processing')
                    ->where('started_at', '<=', now()->subHour())
                    ->update([
                        'status' => 'failed',
                        'exception' => 'Job timeout - exceeded 1 hour',
                        'finished_at' => now(),
                    ]);
            } catch (\Exception $e) {
                Log::error('Failed in queue loop', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    protected function updateParentProgress($parentUuid): void
    {
        try {
            $parent = JobHistory::where('uuid', $parentUuid)
                ->whereNull('parent_uuid')
                ->first();

            if (! $parent) {
                Log::warning('Parent job not found for progress update', [
                    'parent_uuid' => $parentUuid,
                ]);

                return;
            }

            $children = JobHistory::where('parent_uuid', $parentUuid)->get();
            $totalChildren = $children->count();

            if ($totalChildren === 0) {
                Log::warning('No child jobs found for parent', [
                    'parent_uuid' => $parentUuid,
                ]);

                return;
            }

            $completedChildren = $children->where('status', 'completed')->count();
            $failedChildren = $children->where('status', 'failed')->count();
            $processingChildren = $children->where('status', 'processing')->count();

            // Calculate progress and round to nearest integer
            $progress = round(($completedChildren / $totalChildren) * 100);

            // Determine parent status based on child jobs
            $status = match (true) {
                $failedChildren > 0 => 'failed',
                $completedChildren === $totalChildren => 'completed',
                $processingChildren > 0 => 'processing',
                default => $parent->status
            };

            $parent->progress = $progress;
            $parent->status = $status;

            if ($status === 'completed' || $status === 'failed') {
                $parent->finished_at = now();

                // If failed, include information about failed children
                if ($status === 'failed') {
                    $failedJobs = $children->where('status', 'failed')
                        ->pluck('name')
                        ->implode(', ');
                    $parent->exception = "Failed jobs: {$failedJobs}";
                }
            }

            $parent->save();

            Log::debug('Updated parent job progress', [
                'parent_uuid' => $parentUuid,
                'total_children' => $totalChildren,
                'completed_children' => $completedChildren,
                'failed_children' => $failedChildren,
                'processing_children' => $processingChildren,
                'progress' => $progress,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update parent progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'parent_uuid' => $parentUuid,
            ]);
        }
    }
}
