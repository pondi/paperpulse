<?php

use App\Enums\DeletedReason;
use App\Models\Document;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\User;
use App\Services\Files\FileCleanupService;
use App\Services\Files\FileDeletionService;
use App\Services\StorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed', 's3_original_path' => 'documents/'.$this->owner->id.'/original.pdf']);
    Storage::disk('paperpulse')->put($this->file->s3_original_path, 'source');
    app(FileDeletionService::class)->deleteFile($this->file, $this->owner->id);
    File::withTrashed()->whereKey($this->file->id)->update(['deleted_at' => now()->subDays(31)]);
    FileCleanupManifest::first()->update(['available_at' => now()->subDay()]);
});

test('false storage deletion retains file and per-object progress for a successful later run', function (): void {
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->andReturn(false);
    $this->app->instance(StorageService::class, $storage);
    Artisan::call('cleanup:soft-deleted');
    expect(File::withTrashed()->find($this->file->id))->not->toBeNull()
        ->and(FileCleanupManifest::first()->completed_at)->toBeNull()
        ->and(FileCleanupManifest::first()->last_error)->toContain('Storage refused deletion');
    expect(Artisan::output())->toContain('0 S3 files removed');

    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->andReturn(true);
    $this->app->instance(StorageService::class, $storage);
    $this->app->forgetInstance(FileCleanupService::class);
    Artisan::call('cleanup:soft-deleted');
    expect(File::withTrashed()->find($this->file->id))->toBeNull()
        ->and(FileCleanupManifest::first()->completed_at)->not->toBeNull();
});

test('a failed object is retried without repeating a successful variant deletion', function (): void {
    $manifest = FileCleanupManifest::first();
    $manifest->update(['objects' => [
        'first' => ['path' => 'documents/'.$this->owner->id.'/first.pdf', 'done' => false],
        'second' => ['path' => 'documents/'.$this->owner->id.'/second.pdf', 'done' => false],
    ]]);
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->with('documents/'.$this->owner->id.'/first.pdf')->once()->andReturn(true);
    $storage->shouldReceive('deleteFile')->with('documents/'.$this->owner->id.'/second.pdf')->once()->andThrow(new RuntimeException('S3 unavailable'));
    (new FileCleanupService($storage))->process($manifest);
    expect($manifest->fresh()->objects['first']['done'])->toBeTrue()
        ->and($manifest->fresh()->objects['second']['done'])->toBeFalse();
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->with('documents/'.$this->owner->id.'/second.pdf')->once()->andReturn(true);
    (new FileCleanupService($storage))->process($manifest);
    expect($manifest->fresh()->completed_at)->not->toBeNull();
});

test('a live retained copy protects shared object paths from cleanup', function (): void {
    $retained = File::factory()->create(['user_id' => $this->owner->id, 's3_original_path' => $this->file->s3_original_path]);
    $manifest = FileCleanupManifest::first();
    $manifest->update(['objects' => [$this->file->s3_original_path => ['path' => $this->file->s3_original_path, 'done' => false]]]);
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldNotReceive('deleteFile');
    (new FileCleanupService($storage))->process($manifest);
    expect($retained->fresh()->trashed())->toBeFalse();
    Storage::disk('paperpulse')->assertExists($retained->s3_original_path);
});

test('restored sources are never deleted by pending cleanup', function (): void {
    app(FileDeletionService::class)->restoreFile($this->file, $this->owner->id);
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldNotReceive('deleteFile');
    (new FileCleanupService($storage))->process(FileCleanupManifest::first());
    Storage::disk('paperpulse')->assertExists($this->file->s3_original_path);
});

test('search index failure retains durable removal work and entity metadata', function (): void {
    $source = File::factory()->create(['user_id' => $this->owner->id]);
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $source->id]);
    app(FileDeletionService::class)->deleteFile($document->file, $this->owner->id);
    $manifest = FileCleanupManifest::where('file_id', $document->file_id)->first();
    $storage = Mockery::mock(StorageService::class);
    $service = Mockery::mock(FileCleanupService::class, [$storage])->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('removeSearchRecord')->once()->andThrow(new RuntimeException('Index offline'));
    $result = $service->process($manifest);
    expect($result['failed'])->toBe(1)
        ->and($manifest->fresh()->search_records[0]['done'])->toBeFalse()
        ->and($service->pendingSearchRecords()[Document::class])->toContain($document->id);
});

test('search cleanup can recover after an index outage without losing its identity', function (): void {
    $source = File::factory()->create(['user_id' => $this->owner->id]);
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $source->id]);
    app(FileDeletionService::class)->deleteFile($source, $this->owner->id);
    $manifest = FileCleanupManifest::where('file_id', $source->id)->first();
    $storage = Mockery::mock(StorageService::class);
    $service = Mockery::mock(FileCleanupService::class, [$storage])->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('removeSearchRecord')->once()->andThrow(new RuntimeException('Index offline'));
    $service->process($manifest);
    $service = new FileCleanupService($storage);
    $service->process($manifest);
    expect($manifest->fresh()->search_records[0])->toBe(['type' => Document::class, 'id' => $document->id, 'done' => true]);
});

test('reprocess cleanup cannot purge a source still referenced by a live extraction', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id]);
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id]);
    $file->deleted_reason = DeletedReason::Reprocess;
    $file->save();
    $file->delete();
    File::withTrashed()->whereKey($file->id)->update(['deleted_at' => now()->subDays(31)]);
    Artisan::call('cleanup:soft-deleted', ['--include-reprocess' => true]);

    expect(File::withTrashed()->find($file->id))->not->toBeNull()
        ->and($document->fresh()->trashed())->toBeFalse();
});
