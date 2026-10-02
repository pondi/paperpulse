<?php

declare(strict_types=1);

use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Services\File\FileStorageService;
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

it('leaves other jobs files intact when metadata is missing', function () {
    Storage::disk('local')->put('uploads/other-guid.png', 'keep');
    touch(Storage::disk('local')->path('uploads/other-guid.png'), time() - 90000);
    $job = new DeleteWorkingFiles($this->jobId);
    $job->handle();

    Storage::disk('local')->assertExists('uploads/other-guid.png');
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

it('cleans every artifact in the private job directory on success and terminal failure', function (bool $failure): void {
    foreach (['source.docx', 'preview.png', 'archive.pdf', 'nested/page.tiff'] as $name) {
        Storage::disk('local')->put('uploads/'.$this->jobId.'/'.$name, 'artifact');
    }
    Storage::disk('local')->put('uploads/another-job/source.png', 'keep');
    $job = new DeleteWorkingFiles($this->jobId);
    if ($failure) {
        $job->failed(new RuntimeException('Terminal failure'));
    } else {
        $job->handle();
    }
    expect(Storage::disk('local')->directoryExists('uploads/'.$this->jobId))->toBeFalse();
    Storage::disk('local')->assertExists('uploads/another-job/source.png');
})->with([false, true]);

it('sweeps all extensions while preserving old directories owned by active jobs and pending handoffs', function (): void {
    foreach (['active', 'pending-upload', 'completed', 'untracked'] as $id) {
        Storage::disk('local')->put('uploads/'.$id.'/source.docx', 'artifact');
        touch(Storage::disk('local')->path('uploads/'.$id.'/source.docx'), time() - 90000);
    }
    JobHistory::create(['uuid' => 'active', 'status' => 'processing', 'name' => 'Active', 'queue' => 'documents']);
    JobHistory::create(['uuid' => 'completed', 'status' => 'completed', 'name' => 'Done', 'queue' => 'documents']);
    $file = File::factory()->create();
    FileProcessingRequest::create(['job_id' => 'pending-upload', 'user_id' => $file->user_id, 'file_id' => $file->id,
        'file_type' => 'document', 'guid' => $file->guid, 'extension' => 'docx', 'storage_disk' => 'paperpulse', 'original_path' => 'original.docx', 'state' => 'upload_pending']);
    expect(app(FileStorageService::class)->cleanupOldWorkingFiles())->toBe(2);
    Storage::disk('local')->assertExists('uploads/active/source.docx');
    Storage::disk('local')->assertExists('uploads/pending-upload/source.docx');
    Storage::disk('local')->assertMissing('uploads/completed/source.docx');
    Storage::disk('local')->assertMissing('uploads/untracked/source.docx');
    $this->artisan('files:cleanup-working')->expectsOutput('Removed 0 working files.')->assertSuccessful();
});

it('stores working bytes with private directory and file permissions', function (): void {
    $path = app(FileStorageService::class)->storeWorkingContent('office bytes', $this->jobId, 'docx');
    expect($path)->toBe(Storage::disk('local')->path('uploads/'.$this->jobId.'/source.docx'))
        ->and(fileperms(dirname($path)) & 0777)->toBe(0700)
        ->and(fileperms($path) & 0777)->toBe(0600);
});
