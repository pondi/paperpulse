<?php

use App\Contracts\Services\FileStorageContract;
use App\Exceptions\DuplicateFileException;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\User;
use App\Services\File\FileStorageService;
use App\Services\FileProcessingService;
use App\Services\Files\FileDuplicationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('paperpulse');
    Bus::fake();
    $this->owner = User::factory()->create();
    $this->data = ['content' => '%PDF-1.4 document', 'fileName' => 'document.pdf', 'extension' => 'pdf', 'mimeType' => 'application/pdf', 'size' => 17, 'source' => 'upload'];
});

it('rejects an upload claimed after its optimistic duplicate check', function (): void {
    $storage = Mockery::mock(FileStorageService::class)->makePartial();
    $existing = null;
    $storage->shouldReceive('storeWorkingContent')->once()->andReturnUsing(function () use (&$existing): string {
        $existing = File::factory()->create([
            'user_id' => $this->owner->id,
            'file_hash' => hash('sha256', $this->data['content']),
        ]);

        return '/tmp/unused-deduplication-source.pdf';
    });
    $this->app->instance(FileStorageContract::class, $storage);
    $this->app->instance(FileStorageService::class, $storage);

    try {
        app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
        test()->fail('Expected the existing claim to win.');
    } catch (DuplicateFileException $exception) {
        expect($exception->getExistingFile()->id)->toBe($existing->id);
    }

    expect(File::count())->toBe(1)->and(FileProcessingRequest::count())->toBe(0);
    Bus::assertNothingDispatched();
    expect(Storage::disk('paperpulse')->allFiles())->toBe([]);
});

it('keeps one owned file and chain across ingestion sources', function (): void {
    $first = app(FileProcessingService::class)->processFile($this->data, 'document', $this->owner->id);
    foreach (['upload', 'api', 'pulsedav', 'bulk_upload'] as $source) {
        $data = array_replace($this->data, ['source' => $source]);
        expect(fn () => app(FileProcessingService::class)->processFile($data, 'document', $this->owner->id))
            ->toThrow(DuplicateFileException::class);
    }
    expect(File::count())->toBe(1)->and(FileProcessingRequest::count())->toBe(1)
        ->and(File::first()->id)->toBe($first['fileId']);
});

it('permits independent users and reuploads after deletion or unusable failure', function (): void {
    $service = app(FileProcessingService::class);
    $first = $service->processFile($this->data, 'document', $this->owner->id);
    $other = User::factory()->create();
    $second = $service->processFile($this->data, 'document', $other->id);
    expect($second['fileId'])->not->toBe($first['fileId']);

    File::findOrFail($first['fileId'])->delete();
    $third = $service->processFile($this->data, 'document', $this->owner->id);
    File::findOrFail($third['fileId'])->update(['status' => 'failed']);
    $fourth = $service->processFile($this->data, 'document', $this->owner->id);
    expect($fourth['fileId'])->not->toBe($third['fileId']);
});

it('uses the earliest usable active file and retains completed extractions as claims', function (): void {
    $hash = hash('sha256', 'owned-content');
    File::factory()->create(['user_id' => $this->owner->id, 'file_hash' => $hash, 'status' => 'completed']);
    $completed = File::factory()->create(['user_id' => $this->owner->id, 'file_hash' => $hash, 'status' => 'completed']);
    ExtractableEntity::factory()->create(['user_id' => $this->owner->id, 'file_id' => $completed->id]);
    File::factory()->create(['user_id' => $this->owner->id, 'file_hash' => $hash]);
    $this->actingAs(User::factory()->create());

    expect(app(FileDuplicationService::class)->findDuplicateByHash($hash, $this->owner->id)->id)->toBe($completed->id);
});
