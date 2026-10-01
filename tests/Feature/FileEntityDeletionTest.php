<?php

use App\Enums\DeletedReason;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\User;
use App\Services\Files\FileDeletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

function lifecycleDocument(File $file, bool $junction = true): Document
{
    $document = Document::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    if ($junction) {
        ExtractableEntity::create(['file_id' => $file->id, 'user_id' => $file->user_id, 'entity_type' => 'document', 'entity_id' => $document->id, 'extracted_at' => now()]);
    }

    return $document;
}

beforeEach(function (): void {
    Storage::fake('paperpulse');
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create([
        'user_id' => $this->owner->id, 'file_type' => 'document', 'fileExtension' => 'pdf',
        's3_original_path' => 'documents/'.$this->owner->id.'/shared/original.pdf',
        's3_image_path' => 'documents/'.$this->owner->id.'/shared/preview.jpg',
    ]);
    $this->file->update(['s3_original_path' => 'documents/'.$this->owner->id.'/'.$this->file->guid.'/original.pdf']);
    Storage::disk('paperpulse')->put($this->file->s3_original_path, 'original');
    Storage::disk('paperpulse')->put($this->file->s3_image_path, 'preview');
});

test('single document deletion preserves every source variant for a sibling entity', function (): void {
    $first = lifecycleDocument($this->file);
    $sibling = lifecycleDocument($this->file);

    $this->actingAs($this->owner)->delete(route('documents.destroy', $first))->assertRedirect();

    Storage::disk('paperpulse')->assertExists([$this->file->s3_original_path, $this->file->s3_image_path]);
    expect($first->fresh()->trashed())->toBeTrue()
        ->and($sibling->fresh()->trashed())->toBeFalse()
        ->and($this->file->fresh()->trashed())->toBeFalse()
        ->and(FileCleanupManifest::first()->objects)->toBe([]);
    Storage::disk('paperpulse')->assertExists([$this->file->s3_original_path, $this->file->s3_image_path]);
});

test('the final entity records durable cleanup without deleting assets inside the transaction', function (): void {
    $document = lifecycleDocument($this->file);
    app(FileDeletionService::class)->deleteEntity($document, $this->owner->id);

    expect($document->fresh()->deleted_reason)->toBe(DeletedReason::UserDelete)
        ->and($this->file->fresh()->trashed())->toBeTrue()
        ->and(ExtractableEntity::withTrashed()->first()->trashed())->toBeTrue();
    $manifest = FileCleanupManifest::firstOrFail();
    expect($manifest->objects)->toHaveKey($this->file->s3_original_path)
        ->and($manifest->objects)->toHaveKey($this->file->s3_image_path)
        ->and($manifest->search_records)->toContain(['type' => Document::class, 'id' => $document->id, 'done' => false]);
    Storage::disk('paperpulse')->assertExists($this->file->s3_original_path);
});

test('a live entity without a junction still keeps its source', function (): void {
    $document = lifecycleDocument($this->file);
    lifecycleDocument($this->file, false);
    app(FileDeletionService::class)->deleteEntity($document, $this->owner->id);

    expect($this->file->fresh()->trashed())->toBeFalse();
});

test('a direct service caller cannot delete a foreign entity', function (): void {
    $document = lifecycleDocument($this->file);
    $foreignUser = User::factory()->create();
    expect(fn () => app(FileDeletionService::class)->deleteEntity($document, $foreignUser->id))
        ->toThrow(AuthorizationException::class);
    expect($document->fresh()->trashed())->toBeFalse()->and(FileCleanupManifest::count())->toBe(0);
});

test('repeating entity deletion does not duplicate cleanup work', function (): void {
    $document = lifecycleDocument($this->file);
    app(FileDeletionService::class)->deleteEntity($document, $this->owner->id);
    app(FileDeletionService::class)->deleteEntity($document, $this->owner->id);

    expect(FileCleanupManifest::count())->toBe(1)
        ->and(FileCleanupManifest::first()->search_records)->toHaveCount(1);
});
