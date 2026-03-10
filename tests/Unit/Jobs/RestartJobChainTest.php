<?php

declare(strict_types=1);

use App\Jobs\System\RestartJobChain;
use App\Models\JobHistory;
use App\Services\JobChainService;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// --- Construction ---

it('sets job name and original job ID', function () {
    $job = new RestartJobChain('new-job-id', 'original-job-id');

    expect($job->getJobID())->toBe('new-job-id');
    expect($job->getJobName())->toBe('Restart Job Chain');
});

// --- Successful restart ---

it('delegates to JobChainService and succeeds', function () {
    $jobId = (string) Str::uuid();
    $originalJobId = (string) Str::uuid();

    $job = new RestartJobChain($jobId, $originalJobId);

    JobMetadataPersistence::store($jobId, ['jobName' => 'Restart']);

    $mock = Mockery::mock(JobChainService::class);
    $mock->shouldReceive('restartJobChain')
        ->once()
        ->with($originalJobId)
        ->andReturn([
            'success' => true,
            'message' => 'Restarted from ProcessReceipt',
            'restart_point' => 'ProcessReceipt',
            'jobs_count' => 3,
        ]);

    app()->instance(JobChainService::class, $mock);

    $job->handle();

    // Verify job history shows completed
    $history = JobHistory::where('uuid', $job->getUUID())->first();
    expect($history)->not->toBeNull();
    expect($history->status)->toBe('completed');
});

// --- Failed restart ---

it('throws when JobChainService restart fails', function () {
    $jobId = (string) Str::uuid();
    $originalJobId = (string) Str::uuid();

    $job = new RestartJobChain($jobId, $originalJobId);

    JobMetadataPersistence::store($jobId, ['jobName' => 'Restart']);

    $mock = Mockery::mock(JobChainService::class);
    $mock->shouldReceive('restartJobChain')
        ->once()
        ->with($originalJobId)
        ->andReturn([
            'success' => false,
            'message' => 'No restart point found',
        ]);

    app()->instance(JobChainService::class, $mock);

    $job->handle();
})->throws(Exception::class, 'No restart point found');
