<?php

declare(strict_types=1);

use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\JobHistory;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->jobId = (string) Str::uuid();
});

// --- Standard cleanup with GUID ---

it('deletes working files by GUID', function () {
    Storage::disk('local')->put('uploads/test-guid.jpg', 'image-data');
    Storage::disk('local')->put('uploads/test-guid.pdf', 'pdf-data');

    JobMetadataPersistence::store($this->jobId, [
        'fileGuid' => 'test-guid',
        'jobName' => 'Test Job',
    ]);

    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    Storage::disk('local')->assertMissing('uploads/test-guid.jpg');
    Storage::disk('local')->assertMissing('uploads/test-guid.pdf');
});

it('does not delete files with non-matching GUIDs', function () {
    Storage::disk('local')->put('uploads/other-guid.jpg', 'keep-this');
    Storage::disk('local')->put('uploads/test-guid.jpg', 'delete-this');

    JobMetadataPersistence::store($this->jobId, [
        'fileGuid' => 'test-guid',
        'jobName' => 'Test',
    ]);

    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    Storage::disk('local')->assertExists('uploads/other-guid.jpg');
    Storage::disk('local')->assertMissing('uploads/test-guid.jpg');
});

// --- Cache cleanup ---

it('cleans up cache entries after file deletion', function () {
    Cache::put("job.{$this->jobId}.fileMetaData", ['some' => 'data'], 3600);
    Cache::put("job.{$this->jobId}.receiptMetaData", ['other' => 'data'], 3600);

    JobMetadataPersistence::store($this->jobId, [
        'fileGuid' => 'no-files-guid',
        'jobName' => 'Test',
    ]);

    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    expect(Cache::get("job.{$this->jobId}.fileMetaData"))->toBeNull();
    expect(Cache::get("job.{$this->jobId}.receiptMetaData"))->toBeNull();
});

// --- No metadata fallback ---

it('handles missing metadata gracefully with fallback cleanup', function () {
    // Don't store any metadata — simulates already-cleaned-up state
    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    // Job should complete without throwing
    $history = JobHistory::where('parent_uuid', $this->jobId)
        ->where('name', 'Delete Working Files')
        ->first();

    expect($history->status)->toBe('completed');
});

// --- JobHistory tracking ---

it('creates completed job history entry', function () {
    JobMetadataPersistence::store($this->jobId, [
        'fileGuid' => 'some-guid',
        'jobName' => 'Test',
    ]);

    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    $history = JobHistory::where('parent_uuid', $this->jobId)
        ->where('name', 'Delete Working Files')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->status)->toBe('completed');
    expect($history->progress)->toBe(100);
});
