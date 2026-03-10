<?php

declare(strict_types=1);

use App\Models\JobHistory;
use App\Services\JobChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new JobChainService;
});

// --- getJobChainStatus ---

it('returns not found for non-existent job', function () {
    $result = $this->service->getJobChainStatus((string) Str::uuid());

    expect($result['found'])->toBeFalse();
});

it('returns status for existing job chain', function () {
    $uuid = (string) Str::uuid();

    $parent = JobHistory::create([
        'uuid' => $uuid,
        'parent_uuid' => null,
        'name' => 'TestJob',
        'queue' => 'default',
        'status' => 'processing',
        'order_in_chain' => 0,
    ]);

    $result = $this->service->getJobChainStatus($uuid);

    expect($result['found'])->toBeTrue();
    expect($result['job_id'])->toBe($uuid);
    expect($result['name'])->toBe('TestJob');
    expect($result['status'])->toBe('processing');
});

it('indicates can_restart when a task has failed', function () {
    $uuid = (string) Str::uuid();

    $parent = JobHistory::create([
        'uuid' => $uuid,
        'parent_uuid' => null,
        'name' => 'TestJob',
        'queue' => 'receipts',
        'status' => 'failed',
        'order_in_chain' => 0,
    ]);

    JobHistory::create([
        'uuid' => (string) Str::uuid(),
        'parent_uuid' => $uuid,
        'name' => 'ProcessFile',
        'queue' => 'receipts',
        'status' => 'completed',
        'order_in_chain' => 1,
    ]);

    JobHistory::create([
        'uuid' => (string) Str::uuid(),
        'parent_uuid' => $uuid,
        'name' => 'ProcessReceipt',
        'queue' => 'receipts',
        'status' => 'failed',
        'order_in_chain' => 2,
    ]);

    $result = $this->service->getJobChainStatus($uuid);

    expect($result['can_restart'])->toBeTrue();
    expect($result['tasks'])->toHaveCount(2);
});

it('cannot restart when all tasks are completed', function () {
    $uuid = (string) Str::uuid();

    JobHistory::create([
        'uuid' => $uuid,
        'parent_uuid' => null,
        'name' => 'TestJob',
        'queue' => 'receipts',
        'status' => 'completed',
        'order_in_chain' => 0,
    ]);

    JobHistory::create([
        'uuid' => (string) Str::uuid(),
        'parent_uuid' => $uuid,
        'name' => 'ProcessFile',
        'queue' => 'receipts',
        'status' => 'completed',
        'order_in_chain' => 1,
    ]);

    $result = $this->service->getJobChainStatus($uuid);

    expect($result['can_restart'])->toBeFalse();
});

// --- restartJobChain ---

it('fails to restart non-existent job chain', function () {
    $result = $this->service->restartJobChain((string) Str::uuid());

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('not found');
});

it('fails to restart when all tasks completed', function () {
    $uuid = (string) Str::uuid();

    JobHistory::create([
        'uuid' => $uuid,
        'parent_uuid' => null,
        'name' => 'TestJob',
        'queue' => 'receipts',
        'status' => 'completed',
        'order_in_chain' => 0,
    ]);

    JobHistory::create([
        'uuid' => (string) Str::uuid(),
        'parent_uuid' => $uuid,
        'name' => 'ProcessFile',
        'queue' => 'receipts',
        'status' => 'completed',
        'order_in_chain' => 1,
    ]);

    $result = $this->service->restartJobChain($uuid);

    expect($result['success'])->toBeFalse();
});
