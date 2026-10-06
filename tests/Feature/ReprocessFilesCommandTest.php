<?php

use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Files\ClassifyFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FileReprocessingService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\StorageService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Bus::fake();
    config(['ai.file_processing_provider' => 'gemini']);
    $this->storage = $this->mock(StorageService::class);
    $this->storage->shouldReceive('existsInStorage')->andReturnTrue()->byDefault();
});

it('refreshes files across owners and statuses using the current pipeline in chunks', function (): void {
    $files = collect(['completed', 'failed', 'pending', 'needs_review'])->map(fn (string $status): File => File::factory()->create(['status' => $status, 's3_original_path' => 'original.pdf', 'fileExtension' => 'pdf']));
    $prior = Receipt::factory()->create(['file_id' => $files[0]->id, 'user_id' => $files[0]->user_id]);
    $oldId = (string) Str::uuid();
    JobMetadataPersistence::store($oldId, ['fileId' => $files[0]->id, 'processingProvider' => 'ocr-only']);
    JobHistory::where('uuid', $oldId)->update(['status' => 'completed']);
    $originalGeneration = $files[0]->fresh()->meta['processing_generation'];
    $this->actingAs($files[0]->user);

    $this->artisan('files:reprocess', ['--all' => true, '--chunk' => 1, '--no-interaction' => true])
        ->expectsOutputToContain('Queued: 4; failed to queue: 0; skipped: 0.')
        ->assertSuccessful();

    Bus::assertDispatchedTimes(ProcessFile::class, 4);
    Bus::assertChained([ProcessFile::class, ProcessFileGemini::class, DeleteWorkingFiles::class]);
    foreach ($files as $file) {
        $parent = $file->processingJobs()->latest('id')->first();
        expect($parent->metadata['processingProvider'])->toBe('gemini')
            ->and($parent->metadata['pipeline'])->toBe('gemini')
            ->and($parent->metadata['userId'])->toBe($file->user_id)
            ->and($parent->metadata['metadata']['reprocessing'])->toBeTrue()
            ->and($file->fresh()->status)->toBe('pending');
    }
    expect($files[0]->fresh()->meta['processing_generation'])->not->toBe($originalGeneration);
    $this->assertNotSoftDeleted($prior);
});

it('previews refreshes without changing data caches or dispatching jobs', function (): void {
    $file = File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    ProcessingStageCache::remember($file->user_id, 'hash', 'classification', [], fn (): array => ['type' => 'receipt'], $file->guid);
    $key = ProcessingStageCache::key($file->user_id, 'hash', 'classification', []);
    $attributes = $file->fresh()->getAttributes();
    $this->storage->shouldNotReceive('existsInStorage');

    $this->artisan('files:reprocess', ['--all' => true, '--dry-run' => true])->assertSuccessful();

    expect($file->fresh()->getAttributes())->toBe($attributes)
        ->and(Cache::get($key))->toBe(['type' => 'receipt'])->and(JobHistory::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('clears cached processing stages for refreshed files while preserving unrelated caches', function (): void {
    $file = File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    ProcessingStageCache::remember($file->user_id, 'hash', 'classification', [], fn (): array => ['type' => 'receipt'], $file->guid);
    $key = ProcessingStageCache::key($file->user_id, 'hash', 'classification', []);
    Cache::put('unrelated', 'retained');

    $this->artisan('files:reprocess', ['--all' => true, '--no-interaction' => true])->assertSuccessful();

    expect(Cache::has($key))->toBeFalse()->and(Cache::get('unrelated'))->toBe('retained');
});

it('excludes deleted and unstored files', function (): void {
    $deleted = File::factory()->create(['status' => 'completed', 's3_original_path' => 'deleted.pdf']);
    $deleted->delete();
    File::factory()->create(['status' => 'completed', 's3_original_path' => null]);
    File::factory()->create(['status' => 'completed', 's3_original_path' => '']);
    $this->storage->shouldNotReceive('existsInStorage');

    $this->artisan('files:reprocess', ['--all' => true, '--no-interaction' => true])->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('skips active processing unless explicitly forced', function (string $activeState): void {
    $file = File::factory()->create(['status' => $activeState === 'file' ? 'processing' : 'pending', 's3_original_path' => 'original.pdf']);
    if ($activeState === 'job') {
        JobMetadataPersistence::store((string) Str::uuid(), ['fileId' => $file->id]);
    } elseif ($activeState === 'request') {
        FileProcessingRequest::query()->create([
            'job_id' => (string) Str::uuid(), 'file_id' => $file->id, 'user_id' => $file->user_id,
            'file_type' => $file->file_type, 'guid' => $file->guid, 'extension' => $file->fileExtension,
            'storage_disk' => 'paperpulse', 'original_path' => $file->s3_original_path, 'state' => 'dispatching',
        ]);
    }

    $this->artisan('files:reprocess', ['--all' => true, '--no-interaction' => true])->assertSuccessful();
    Bus::assertNothingDispatched();

    $this->artisan('files:reprocess', ['--all' => true, '--force' => true])->assertSuccessful();
    Bus::assertDispatchedTimes(ProcessFile::class, 1);
})->with(['file', 'job', 'request']);

it('checks for active work again when handing off a selected file', function (): void {
    $file = File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    JobMetadataPersistence::store((string) Str::uuid(), ['fileId' => $file->id]);
    $generation = $file->fresh()->meta['processing_generation'];
    $this->storage->shouldNotReceive('existsInStorage');

    $result = app(FileReprocessingService::class)->reprocessFile($file, force: true, provider: 'gemini', fresh: true, skipActive: true);

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('active processing')
        ->and($file->fresh()->meta['processing_generation'])->toBe($generation);
    Bus::assertNothingDispatched();
});

it('applies owner type cursor and limit filters to explicit file IDs', function (): void {
    $owner = User::factory()->create();
    $files = File::factory()->count(4)->create(['user_id' => $owner->id, 'status' => 'completed', 'file_type' => 'document', 's3_original_path' => 'original.pdf']);
    $otherOwner = File::factory()->create(['status' => 'completed', 'file_type' => 'document', 's3_original_path' => 'other.pdf']);
    $receipt = File::factory()->create(['user_id' => $owner->id, 'status' => 'completed', 'file_type' => 'receipt', 's3_original_path' => 'receipt.pdf']);

    $this->artisan('files:reprocess', [
        '--all' => true, '--user' => $owner->id, '--type' => 'document', '--after-id' => $files[0]->id,
        '--file-id' => [...$files->modelKeys(), $otherOwner->id, $receipt->id], '--limit' => 2, '--chunk' => 1, '--no-interaction' => true,
    ])->assertSuccessful();

    expect(JobHistory::whereNull('parent_uuid')->orderBy('file_id')->pluck('file_id')->all())->toBe([$files[1]->id, $files[2]->id]);
    Bus::assertDispatchedTimes(ProcessFile::class, 2);
});

it('continues after a missing source and returns failure while retaining its prior extraction', function (): void {
    $missing = File::factory()->create(['status' => 'completed', 's3_original_path' => 'missing.pdf', 'meta' => ['processing_generation' => 'prior']]);
    $prior = Receipt::factory()->create(['file_id' => $missing->id, 'user_id' => $missing->user_id]);
    $valid = File::factory()->create(['status' => 'completed', 's3_original_path' => 'valid.pdf']);
    $this->storage->shouldReceive('existsInStorage')->with('missing.pdf')->once()->andReturnFalse();

    $this->artisan('files:reprocess', ['--all' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Queued: 1; failed to queue: 1; skipped: 0.')->assertFailed();

    expect($missing->fresh()->status)->toBe('completed')->and($missing->fresh()->meta['processing_generation'])->toBe('prior')
        ->and($valid->fresh()->status)->toBe('pending');
    $this->assertNotSoftDeleted($prior);
});

it('continues after a dispatch failure and rolls back the failed handoff', function (): void {
    $files = File::factory()->count(2)->create(['status' => 'completed', 's3_original_path' => 'original.pdf', 'meta' => ['processing_generation' => 'prior']]);
    $dispatcher = $this->mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue offline'));
    $dispatcher->shouldReceive('dispatch')->once();

    $this->artisan('files:reprocess', ['--all' => true, '--no-interaction' => true])->assertFailed();

    expect($files[0]->fresh()->status)->toBe('completed')
        ->and($files[0]->fresh()->meta['processing_generation'])->toBe('prior')
        ->and($files[1]->fresh()->status)->toBe('pending')->and(JobHistory::count())->toBe(1);
});

it('keeps failed-only retry behavior and the previous pipeline by default', function (): void {
    $file = File::factory()->create(['status' => 'failed', 's3_original_path' => 'original.pdf']);
    File::factory()->create(['status' => 'completed', 's3_original_path' => 'completed.pdf']);
    $oldId = (string) Str::uuid();
    JobMetadataPersistence::store($oldId, ['fileId' => $file->id, 'processingProvider' => 'textract+openai']);
    JobHistory::where('uuid', $oldId)->update(['status' => 'failed']);

    $this->artisan('files:reprocess', ['--no-interaction' => true])->assertSuccessful();

    Bus::assertDispatchedTimes(ProcessFile::class, 1);
    Bus::assertChained([ProcessFile::class, ClassifyFile::class, DeleteWorkingFiles::class]);
});

it('uses an explicit provider override and preserves CSV import routing', function (string $extension, array $chain, string $pipeline): void {
    $file = File::factory()->create(['status' => 'completed', 'fileExtension' => $extension, 's3_original_path' => 'original.'.$extension]);

    $this->artisan('files:reprocess', ['--all' => true, '--provider' => 'textract+openai', '--no-interaction' => true])->assertSuccessful();

    Bus::assertChained($chain);
    expect($file->processingJobs()->first()->metadata['pipeline'])->toBe($pipeline);
})->with([
    ['pdf', [ProcessFile::class, ClassifyFile::class, DeleteWorkingFiles::class], 'textract+openai'],
    ['CSV', [ProcessCsvImport::class, DeleteWorkingFiles::class], 'csv'],
]);

it('rejects invalid options before dispatching', function (array $options): void {
    File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    $this->storage->shouldNotReceive('existsInStorage');

    $this->artisan('files:reprocess', $options + ['--no-interaction' => true])->assertExitCode(2);

    Bus::assertNothingDispatched();
})->with([
    [['--limit' => 0]], [['--limit' => 'abc']], [['--chunk' => 0]], [['--chunk' => 1001]],
    [['--after-id' => -1]], [['--user' => 0]], [['--file-id' => ['abc']]],
    [['--type' => 'invoice']], [['--status' => 'unknown']], [['--provider' => 'unknown']],
    [['--all' => true, '--status' => 'failed']],
]);

it('supports all statuses through the status option', function (): void {
    File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);

    $this->artisan('files:reprocess', ['--status' => 'all', '--no-interaction' => true])->assertSuccessful();

    Bus::assertDispatchedTimes(ProcessFile::class, 1);
});

it('shows completed and review statistics without dispatching', function (): void {
    File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    File::factory()->create(['status' => 'needs_review', 'file_type' => 'document', 's3_original_path' => 'review.pdf']);

    $this->artisan('files:reprocess', ['--stats' => true])
        ->expectsTable(['Type', 'Failed', 'Pending', 'Processing', 'Completed', 'Needs review'], [
            ['Receipts', 0, 0, 0, 1, 0],
            ['Documents', 0, 0, 0, 0, 1],
        ])->assertSuccessful();

    Bus::assertNothingDispatched();
});
