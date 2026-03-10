<?php

declare(strict_types=1);

use App\Jobs\BaseJob;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Concrete implementation for testing
class TestConcreteJob extends BaseJob
{
    public bool $shouldFail = false;

    public bool $executed = false;

    public function __construct(string $jobID, bool $shouldFail = false)
    {
        parent::__construct($jobID);
        $this->shouldFail = $shouldFail;
        $this->jobName = 'TestConcreteJob';
    }

    protected function handleJob(): void
    {
        $this->executed = true;

        if ($this->shouldFail) {
            throw new \RuntimeException('Test failure message');
        }
    }
}

// --- Construction ---

it('requires a non-empty job ID', function () {
    new TestConcreteJob('');
})->throws(InvalidArgumentException::class, 'JobID cannot be empty');

it('sets job ID and name on construction', function () {
    $job = new TestConcreteJob('test-job-id');

    expect($job->getJobID())->toBe('test-job-id');
    expect($job->getJobName())->toBe('TestConcreteJob');
});

it('generates a UUID when handle is called', function () {
    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId);

    // Store metadata so handle can proceed
    JobMetadataPersistence::store($jobId, ['jobName' => 'Test']);

    expect($job->getUUID())->toBeNull();

    $job->handle();

    expect($job->getUUID())->not->toBeNull();
});

// --- JobHistory tracking ---

it('creates job history record when handled', function () {
    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId);

    JobMetadataPersistence::store($jobId, ['jobName' => 'Test']);

    $job->handle();

    $history = JobHistory::where('uuid', $job->getUUID())->first();

    expect($history)->not->toBeNull();
    expect($history->name)->toBe('TestConcreteJob');
    expect($history->status)->toBe('completed');
    expect($history->parent_uuid)->toBe($jobId);
});

it('marks job history as failed on exception', function () {
    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId, shouldFail: true);
    $job->uuid = (string) Str::uuid();

    JobMetadataPersistence::store($jobId, ['jobName' => 'Test']);

    // Create the job history record manually since handle will throw
    JobHistory::create([
        'uuid' => $job->uuid,
        'parent_uuid' => $jobId,
        'name' => 'TestConcreteJob',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    // Simulate the failed() callback
    $job->failed(new RuntimeException('Test failure message'));

    $history = JobHistory::where('uuid', $job->uuid)->first();

    expect($history->status)->toBe('failed');
    expect($history->exception)->toContain('Test failure message');
});

// --- File status update on failure ---

it('updates file status to failed when job fails', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'status' => 'processing',
    ]);

    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId, shouldFail: true);
    $job->uuid = (string) Str::uuid();

    JobMetadataPersistence::store($jobId, [
        'jobName' => 'Test',
        'fileId' => $file->id,
    ]);

    JobHistory::create([
        'uuid' => $job->uuid,
        'parent_uuid' => $jobId,
        'name' => 'TestConcreteJob',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    $job->failed(new RuntimeException('Processing error'));

    expect($file->fresh()->status)->toBe('failed');
});

it('does not update file status if already completed', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
    ]);

    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId, shouldFail: true);
    $job->uuid = (string) Str::uuid();

    JobMetadataPersistence::store($jobId, [
        'jobName' => 'Test',
        'fileId' => $file->id,
    ]);

    JobHistory::create([
        'uuid' => $job->uuid,
        'parent_uuid' => $jobId,
        'name' => 'TestConcreteJob',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    $job->failed(new RuntimeException('Late failure'));

    // Status should remain 'completed', not changed to 'failed'
    expect($file->fresh()->status)->toBe('completed');
});

// --- Parent status aggregation ---

it('updates parent job status to completed when all children complete', function () {
    $jobId = (string) Str::uuid();

    // Create parent job
    JobHistory::create([
        'uuid' => $jobId,
        'parent_uuid' => null,
        'name' => 'Parent Job',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    // Create and run child job
    $job = new TestConcreteJob($jobId);
    JobMetadataPersistence::store($jobId, ['jobName' => 'Test']);

    $job->handle();

    $parent = JobHistory::where('uuid', $jobId)->first();
    expect($parent->status)->toBe('completed');
});

// --- Tags ---

it('returns proper tags for queue identification', function () {
    $job = new TestConcreteJob('job-123');
    $job->uuid = 'task-456';

    $tags = $job->tags();

    expect($tags)->toContain('job:job-123');
    expect($tags)->toContain('task:task-456');
    expect($tags)->toContain('type:TestConcreteJob');
});

// --- Double failure prevention ---

it('prevents double handling of failures', function () {
    $jobId = (string) Str::uuid();
    $job = new TestConcreteJob($jobId, shouldFail: true);
    $job->uuid = (string) Str::uuid();

    JobMetadataPersistence::store($jobId, ['jobName' => 'Test']);

    JobHistory::create([
        'uuid' => $job->uuid,
        'parent_uuid' => $jobId,
        'name' => 'TestConcreteJob',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    // First call should work
    $job->failed(new RuntimeException('Error'));
    $history = JobHistory::where('uuid', $job->uuid)->first();
    expect($history->status)->toBe('failed');

    // Reset to processing to detect if second call changes anything
    JobHistory::where('uuid', $job->uuid)->update(['status' => 'processing']);

    // Second call should be no-op due to failureHandled flag
    $job->failed(new RuntimeException('Second error'));
    $history = JobHistory::where('uuid', $job->uuid)->first();
    expect($history->status)->toBe('processing');
});
