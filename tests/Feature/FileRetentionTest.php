<?php

use App\Enums\DeletedReason;
use App\Jobs\Maintenance\CleanupRetainedFiles;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\Invoice;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Files\FileCleanupService;
use App\Services\Files\FileDeletionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function retentionCompletion(File $file, string $status = 'completed', ?Carbon $finishedAt = null, array $metadata = []): JobHistory
{
    return JobHistory::create([
        'uuid' => (string) Str::uuid(), 'file_id' => $file->id, 'name' => 'Processing',
        'queue' => 'documents', 'status' => $status,
        'finished_at' => $finishedAt ?? now()->subDays(31), 'metadata' => $metadata,
    ]);
}

beforeEach(function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $this->owner = User::factory()->create();
    $this->owner->preferences()->create(array_replace(UserPreference::defaultPreferences(), [
        'delete_after_processing' => true, 'file_retention_days' => 30,
    ]));
});

test('retention mode saves and reloads in preferences', function (string $mode): void {
    $payload = array_replace(UserPreference::defaultPreferences(), ['retention_mode' => $mode]);
    $this->actingAs($this->owner)->patch(route('preferences.update'), $payload)->assertSessionHasNoErrors();
    expect($this->owner->preferences()->first()->retention_mode)->toBe($mode);
    $this->owner->refresh();
    $this->get(route('preferences.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Preferences/Index')->where('preferences.retention_mode', $mode));
})->with(['source_only', 'full_delete']);

test('unsupported retention modes are rejected', function (): void {
    $payload = array_replace(UserPreference::defaultPreferences(), ['retention_mode' => 'invalid']);
    $this->actingAs($this->owner)->patch(route('preferences.update'), $payload)->assertSessionHasErrors('retention_mode');
    expect($this->owner->preferences()->first()->retention_mode)->toBe('source_only');
});

test('retention preserves extracted siblings in source mode and removes them in full mode', function (string $mode): void {
    $this->owner->preferences()->update(['retention_mode' => $mode]);
    $file = File::factory()->create([
        'user_id' => $this->owner->id, 'status' => 'completed',
        's3_original_path' => 'documents/'.$this->owner->id.'/source.docx',
        's3_processed_path' => 'documents/'.$this->owner->id.'/processed.pdf',
        's3_archive_path' => 'documents/'.$this->owner->id.'/archive.pdf',
        's3_image_path' => 'documents/'.$this->owner->id.'/preview.jpg',
        'meta' => ['processing_generation' => 'current'],
    ]);
    $entities = collect(FileDeletionService::ENTITY_CLASSES)->map(fn ($class) => $class::factory()->create([
        'user_id' => $this->owner->id, 'file_id' => $file->id,
    ]));
    foreach ([$file->s3_original_path, $file->s3_processed_path, $file->s3_archive_path, $file->s3_image_path] as $path) {
        Storage::disk('paperpulse')->put($path, 'binary');
    }
    retentionCompletion($file);
    (new CleanupRetainedFiles)->handle();
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();
    $this->travel(1)->seconds();
    expect(app(FileCleanupService::class)->process($manifest)['failed'])->toBe(0);

    foreach ($entities as $entity) {
        expect($entity->fresh()->trashed())->toBe($mode === 'full_delete');
    }
    expect($file->fresh()->trashed())->toBe($mode === 'full_delete');
    Storage::disk('paperpulse')->assertMissing([$file->s3_original_path, $file->s3_processed_path, $file->s3_archive_path]);
    if ($mode === 'source_only') {
        Storage::disk('paperpulse')->assertExists($file->s3_image_path);
        expect($file->fresh()->s3_original_path)->toBeNull()
            ->and($file->fresh()->s3_processed_path)->toBeNull()
            ->and($file->fresh()->s3_archive_path)->toBeNull()
            ->and($file->fresh()->s3_image_path)->toBe($file->s3_image_path);
        $updatedAt = $manifest->fresh()->updated_at;
        (new CleanupRetainedFiles)->handle();
        expect($manifest->fresh()->updated_at->eq($updatedAt))->toBeTrue();
    } else {
        Storage::disk('paperpulse')->assertMissing($file->s3_image_path);
        expect($file->fresh()->deleted_reason)->toBe(DeletedReason::UserDelete);
    }
})->with(['source_only', 'full_delete']);

test('retention uses latest completion time and skips unfinished active disabled and foreign files', function (): void {
    $recent = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed', 'created_at' => now()->subYear()]);
    retentionCompletion($recent);
    retentionCompletion($recent, finishedAt: now()->subDay());
    $active = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed']);
    retentionCompletion($active);
    retentionCompletion($active, 'processing');
    $unfinished = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing']);
    retentionCompletion($unfinished);
    $unknown = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed']);
    $other = User::factory()->create();
    $other->preferences()->create(UserPreference::defaultPreferences());
    $foreign = File::factory()->create(['user_id' => $other->id, 'status' => 'completed']);
    retentionCompletion($foreign);

    (new CleanupRetainedFiles)->handle();

    expect(FileCleanupManifest::count())->toBe(0);
    foreach ([$recent, $active, $unfinished, $unknown, $foreign] as $file) {
        expect($file->fresh()->trashed())->toBeFalse();
    }
});

test('a queued source purge waits for active work and rejects an obsolete generation', function (): void {
    $file = File::factory()->create([
        'user_id' => $this->owner->id, 'status' => 'completed', 'meta' => ['processing_generation' => 'old'],
        's3_original_path' => 'documents/'.$this->owner->id.'/source.pdf',
    ]);
    retentionCompletion($file);
    Storage::disk('paperpulse')->put($file->s3_original_path, 'source');
    app(FileDeletionService::class)->retainFile($file, $this->owner->id, now()->subDays(30), true);
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();
    $active = retentionCompletion($file, 'processing');
    $this->travel(1)->seconds();
    app(FileCleanupService::class)->process($manifest);
    Storage::disk('paperpulse')->assertExists($file->s3_original_path);
    expect($manifest->fresh()->completed_at)->toBeNull();
    $active->update(['status' => 'completed', 'finished_at' => now()]);
    $file->update(['meta' => ['processing_generation' => 'new']]);
    $this->travel(1)->seconds();
    app(FileCleanupService::class)->process($manifest);
    Storage::disk('paperpulse')->assertExists($file->s3_original_path);
    expect($manifest->fresh()->completed_at)->not->toBeNull();
    expect($file->fresh()->s3_original_path)->toBe($file->s3_original_path);
    expect(data_get($file->fresh()->meta, 'retention.source_removed_at'))->toBeNull();
});

test('retention does not remove a binary referenced by another active file', function (): void {
    $path = 'documents/'.$this->owner->id.'/shared.pdf';
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed', 's3_original_path' => $path]);
    $sibling = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing', 's3_original_path' => $path]);
    retentionCompletion($file);
    Storage::disk('paperpulse')->put($path, 'source');
    (new CleanupRetainedFiles)->handle();
    $this->travel(1)->seconds();
    app(FileCleanupService::class)->process(FileCleanupManifest::where('file_id', $file->id)->firstOrFail());
    Storage::disk('paperpulse')->assertExists($path);
    expect($sibling->fresh()->s3_original_path)->toBe($path);
});

test('scanner sources follow the owner retention mode after canonical processing completes', function (string $mode): void {
    $this->owner->preferences()->update(['retention_mode' => $mode]);
    $source = PulseDavFile::create([
        'user_id' => $this->owner->id, 's3_path' => 'incoming/'.$this->owner->id.'/invoice.pdf',
        'filename' => 'invoice.pdf', 'uploaded_at' => now()->subDays(40), 'status' => 'completed', 'processed_at' => now()->subDays(31),
    ]);
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed']);
    $invoice = Invoice::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id]);
    retentionCompletion($file, metadata: ['metadata' => ['pulseDavFileId' => $source->id]]);
    Storage::disk('pulsedav')->put($source->s3_path, 'source');
    Storage::disk('pulsedav')->put('archive/'.$source->s3_path, 'archive');

    (new CleanupRetainedFiles)->handle();
    $this->travel(1)->seconds();
    app(FileCleanupService::class)->process(FileCleanupManifest::where('file_id', $file->id)->firstOrFail());

    Storage::disk('pulsedav')->assertMissing([$source->s3_path, 'archive/'.$source->s3_path]);
    expect($invoice->fresh()->trashed())->toBe($mode === 'full_delete');
})->with(['source_only', 'full_delete']);
