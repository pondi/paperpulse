<?php

use App\Enums\DeletedReason;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Files\FileDeletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed']);
});

test('file deletion removes active entities children junctions shares and invalidates processing', function (): void {
    $receipt = Receipt::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id]);
    $lineItem = $receipt->lineItems()->create(['text' => 'Purchased item', 'qty' => 1, 'price' => 10, 'total' => 10]);
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id]);
    $junction = ExtractableEntity::create(['user_id' => $this->owner->id, 'file_id' => $this->file->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'extracted_at' => now()]);
    FileShare::create(['file_id' => $this->file->id, 'file_type' => 'receipt', 'shared_by_user_id' => $this->owner->id, 'shared_with_user_id' => User::factory()->create()->id, 'permission' => 'view', 'shared_at' => now()]);

    app(FileDeletionService::class)->deleteFile($this->file, $this->owner->id);

    foreach ([$receipt, $lineItem, $document, $junction, $this->file] as $model) {
        expect($model->fresh()->trashed())->toBeTrue()->and($model->fresh()->deleted_reason)->toBe(DeletedReason::UserDelete);
    }
    expect(FileShare::count())->toBe(0)
        ->and($this->file->fresh()->meta['processing_generation'])->toBeString()
        ->and(FileCleanupManifest::first()->search_records)->toHaveCount(3);
});

test('restoring a file restores exactly its prior active extraction and keeps shares revoked', function (): void {
    $active = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id]);
    $previouslyDeleted = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id]);
    $previouslyDeleted->deleted_reason = DeletedReason::UserDelete;
    $previouslyDeleted->save();
    $previouslyDeleted->delete();
    $service = app(FileDeletionService::class);
    $service->deleteFile($this->file, $this->owner->id);
    $service->restoreFile($this->file, $this->owner->id);

    expect($active->fresh()->trashed())->toBeFalse()
        ->and($previouslyDeleted->fresh()->trashed())->toBeTrue()
        ->and($this->file->fresh()->trashed())->toBeFalse()
        ->and(FileCleanupManifest::first()->objects)->toBe([]);
});

test('restoration is denied once any source variant has been purged', function (): void {
    $service = app(FileDeletionService::class);
    $service->deleteFile($this->file, $this->owner->id);
    $manifest = FileCleanupManifest::first();
    $objects = $manifest->objects;
    $objects[array_key_first($objects)]['done'] = true;
    $manifest->update(['objects' => $objects]);

    expect(fn () => $service->restoreFile($this->file, $this->owner->id))->toThrow(ValidationException::class);
    expect($this->file->fresh()->trashed())->toBeTrue();
});

test('file lifecycle services enforce ownership without an authenticated HTTP scope', function (): void {
    $foreign = User::factory()->create();
    $service = app(FileDeletionService::class);
    expect(fn () => $service->deleteFile($this->file, $foreign->id))->toThrow(AuthorizationException::class);
    expect(fn () => $service->restoreFile($this->file, $foreign->id))->toThrow(AuthorizationException::class);
    expect($this->file->fresh()->trashed())->toBeFalse();
});

test('legacy deletion backfill leaves ambiguous unfinished files untouched', function (): void {
    $pending = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing']);
    $pending->delete();
    $this->file->delete();
    app(FileDeletionService::class)->backfillLegacyDeletions();

    expect($this->file->fresh()->deleted_reason)->toBe(DeletedReason::UserDelete)
        ->and($pending->fresh()->deleted_reason)->toBeNull();
});
