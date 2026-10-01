<?php

use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->file = File::factory()->create();
    $this->jobId = (string) Str::uuid();
    $this->metadata = ['fileId' => $this->file->id, 'fileExtension' => 'pdf', 'jobName' => 'Durable source'];
});

it('retains exact file owner generation and plan context after cache is cleared', function (): void {
    Bus::fake();
    JobMetadataPersistence::store($this->jobId, $this->metadata);
    (new FileJobChainDispatcher)->dispatch($this->jobId, 'document');
    $beforeFlush = JobMetadataPersistence::retrieve($this->jobId);
    Cache::flush();
    $afterFlush = JobMetadataPersistence::retrieve($this->jobId);

    expect($afterFlush)->toBe($beforeFlush)
        ->and($afterFlush['fileId'])->toBe($this->file->id)
        ->and($afterFlush['userId'])->toBe($this->file->user_id)
        ->and($afterFlush['processingGeneration'])->toBe($this->file->fresh()->meta['processing_generation'])
        ->and($afterFlush['plannedSteps'])->not->toBeEmpty();
});

it('persists and reads metadata when the cache backend throws', function (): void {
    Cache::shouldReceive('put')->andThrow(new RuntimeException('Redis offline'));
    Cache::shouldReceive('get')->never();
    JobMetadataPersistence::store($this->jobId, $this->metadata);
    $metadata = JobMetadataPersistence::retrieve($this->jobId);

    expect($metadata['fileId'])->toBe($this->file->id)
        ->and(JobHistory::where('uuid', $this->jobId)->first()->metadata['userId'])->toBe($this->file->user_id);
});

it('rejects a forged owner before writing durable processing context', function (): void {
    $foreign = User::factory()->create();
    expect(fn () => JobMetadataPersistence::store($this->jobId, $this->metadata + ['userId' => $foreign->id]))
        ->toThrow(AuthorizationException::class);
    expect(JobHistory::where('uuid', $this->jobId)->exists())->toBeFalse();
});

it('does not switch the persisted pipeline when application defaults change', function (): void {
    Bus::fake();
    config(['ai.file_processing_provider' => 'gemini']);
    JobMetadataPersistence::store($this->jobId, $this->metadata);
    Cache::flush();
    config(['ai.file_processing_provider' => 'textract+openai']);
    (new FileJobChainDispatcher)->dispatch($this->jobId, 'document');

    Bus::assertChained([ProcessFile::class, ProcessFileGemini::class, DeleteWorkingFiles::class]);
    expect(JobMetadataPersistence::retrieve($this->jobId)['pipeline'])->toBe('gemini');
});

it('refuses dispatch when neither durable nor legacy metadata exists', function (): void {
    Bus::fake();
    expect(fn () => (new FileJobChainDispatcher)->dispatch($this->jobId, 'document'))
        ->toThrow(RuntimeException::class, 'Missing durable processing metadata');
    Bus::assertNothingDispatched();
});
