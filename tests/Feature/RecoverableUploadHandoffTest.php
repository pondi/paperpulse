<?php

use App\Contracts\Services\FileStorageContract;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\File\FileStorageService;
use App\Services\FileProcessingService;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FileProcessingRequestService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Bus::fake();
    $this->owner = User::factory()->create();
    $this->data = ['content' => '%PDF-1.4 test document', 'fileName' => 'document.pdf', 'extension' => 'pdf', 'mimeType' => 'application/pdf', 'size' => 22, 'source' => 'upload'];
});

it('a database failure writes no untracked permanent object', function (): void {
    File::creating(function (): void {
        throw new RuntimeException('Injected database failure');
    });
    try {
        expect(fn () => app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id))
            ->toThrow(RuntimeException::class, 'Injected database failure');
        expect(Storage::disk('paperpulse')->allFiles())->toBe([])
            ->and(FileProcessingRequest::count())->toBe(0)
            ->and(File::count())->toBe(0);
    } finally {
        File::flushEventListeners();
        File::clearBootedModels();
    }
});

it('a queue failure leaves an accepted upload with one retryable durable request', function (): void {
    Exceptions::fake();
    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue offline'));
    $this->app->instance(FileJobChainDispatcher::class, $dispatcher);
    $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
    $request = FileProcessingRequest::first();

    expect($result['success'])->toBeTrue()->and($result['queue_pending'])->toBeTrue()
        ->and($request->state)->toBe('pending')
        ->and($request->last_error)->toBe('Queue offline')
        ->and(JobHistory::where('uuid', $result['jobId'])->first()->metadata['fileId'])->toBe($result['fileId']);
    Storage::disk('paperpulse')->assertExists($request->original_path);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Queue offline');
    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($result['jobId'], 'document');
    $this->app->instance(FileJobChainDispatcher::class, $dispatcher);
    $this->app->forgetInstance(FileProcessingRequestService::class);
    expect(app(FileProcessingRequestService::class)->recover())->toBe(1)
        ->and($request->fresh()->state)->toBe('dispatched');
});

it('a cache outage still accepts and dispatches the durable upload', function (): void {
    $cacheStore = Cache::store();
    Cache::shouldReceive('put')->andThrow(new RuntimeException('Cache offline'));
    Cache::shouldReceive('forever')->andReturn(true);
    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('store')->andReturn($cacheStore);
    Cache::shouldReceive('driver')->andReturn($cacheStore);
    $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);

    expect($result['success'])->toBeTrue()
        ->and(FileProcessingRequest::first()->state)->toBe('dispatched')
        ->and(JobHistory::where('uuid', $result['jobId'])->first()->metadata['plannedSteps'])->not->toBeEmpty();
});

it('outer transaction rollback performs neither permanent upload nor queue handoff', function (): void {
    DB::beginTransaction();
    try {
        $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
        expect($result['queue_pending'])->toBeTrue();
        expect(Storage::disk('paperpulse')->allFiles())->toBe([]);
        Bus::assertNothingDispatched();
    } finally {
        DB::rollBack();
    }
    expect(FileProcessingRequest::count())->toBe(0)->and(File::count())->toBe(0);
    Storage::disk('paperpulse')->assertDirectoryEmpty('documents');
});

it('a failed storage write surfaces failure while retaining durable asset cleanup references', function (): void {
    Exceptions::fake();
    $storage = Mockery::mock(FileStorageService::class);
    $storage->shouldReceive('generateFileGuid')->andReturn('failed-source');
    $storage->shouldReceive('storeWorkingContent')->andReturn('/tmp/failed-source.pdf');
    $storage->shouldReceive('storeToS3')->once()->andThrow(new RuntimeException('S3 refused write'));
    $this->app->instance(FileStorageContract::class, $storage);
    $this->app->instance(FileStorageService::class, $storage);

    expect(fn () => app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id))
        ->toThrow(RuntimeException::class, 'S3 refused write');
    expect(File::count())->toBe(0)->and(FileProcessingRequest::first()->state)->toBe('cleanup_pending')
        ->and(FileCleanupManifest::first()->objects)->not->toBeEmpty();
    Bus::assertNotDispatched(ProcessFileGemini::class);
    Bus::assertNotDispatched(ProcessFile::class);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'S3 refused write');
});

it('recovers an uploaded object after a persistence crash without losing or duplicating its chain', function (): void {
    Exceptions::fake();
    FileProcessingRequest::updating(function (FileProcessingRequest $request): void {
        if ($request->state === 'pending') {
            throw new RuntimeException('Injected post-upload persistence failure');
        }
    });
    try {
        $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
        $request = FileProcessingRequest::first();
        expect($result['success'])->toBeTrue()->and($result['queue_pending'])->toBeTrue()
            ->and($request->state)->toBe('upload_pending');
        Storage::disk('paperpulse')->assertExists($request->original_path);
        Bus::assertNotDispatched(ProcessFileGemini::class);
        Bus::assertNotDispatched(ProcessFile::class);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Injected post-upload persistence failure');
    } finally {
        FileProcessingRequest::flushEventListeners();
    }
    expect(app(FileProcessingRequestService::class)->recover())->toBe(1)
        ->and(app(FileProcessingRequestService::class)->recover())->toBe(0)
        ->and($request->fresh()->state)->toBe('dispatched')
        ->and(JobHistory::where('uuid', $result['jobId'])->count())->toBe(1);
});

it('deferred upload performs storage and handoff only after the outer transaction commits', function (): void {
    DB::beginTransaction();
    $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
    $request = FileProcessingRequest::first();
    expect($request->state)->toBe('upload_pending');
    Storage::disk('paperpulse')->assertDirectoryEmpty('documents');
    DB::commit();

    expect($request->fresh()->state)->toBe('dispatched');
    Storage::disk('paperpulse')->assertExists($request->original_path);
});

it('recovers an expired handoff claim once and retains the accepted original', function (): void {
    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Worker stopped'));
    $this->app->instance(FileJobChainDispatcher::class, $dispatcher);
    $result = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
    $request = FileProcessingRequest::first();
    $workingPath = $request->working_path;
    expect(is_file($workingPath))->toBeTrue();
    Storage::disk('paperpulse')->assertExists($request->original_path);

    $request->update([
        'state' => 'dispatching', 'claim_token' => (string) Str::uuid(), 'claimed_at' => now()->subMinutes(11),
    ]);
    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($result['jobId'], 'document');
    $this->app->instance(FileJobChainDispatcher::class, $dispatcher);
    $this->app->forgetInstance(FileProcessingRequestService::class);

    expect(app(FileProcessingRequestService::class)->recover())->toBe(1)
        ->and(app(FileProcessingRequestService::class)->recover())->toBe(0)
        ->and($request->fresh()->state)->toBe('dispatched')
        ->and($request->fresh()->attempts)->toBe(2)
        ->and($request->fresh()->working_path)->toBeNull()
        ->and(is_file($workingPath))->toBeFalse();
    Storage::disk('paperpulse')->assertExists($request->original_path);
    $this->assertDatabaseCount('file_processing_requests', 1);
    $this->assertDatabaseCount('files', 1);
});
