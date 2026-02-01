<?php

use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Files\FileDuplicationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Document::disableSearchSyncing();
    Receipt::disableSearchSyncing();
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
});

afterEach(function () {
    Document::enableSearchSyncing();
    Receipt::enableSearchSyncing();
});

it('does not treat a bulk-deleted receipt as a duplicate source', function () {
    $user = User::factory()->create();
    $content = 'same-content';
    $hash = hash('sha256', $content);
    $guid = (string) Str::uuid();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'receipt.pdf',
        'fileExtension' => 'pdf',
        'fileType' => 'application/pdf',
        'fileSize' => 123,
        'status' => 'completed',
        'file_hash' => $hash,
    ]);

    $receipt = Receipt::create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    // Create extractable entity record (required for deduplication check)
    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'receipt',
        'entity_id' => $receipt->id,
        'is_primary' => true,
        'extracted_at' => now(),
    ]);

    $dedupe = app(FileDuplicationService::class);
    expect($dedupe->checkDuplication($content, $user->id)['isDuplicate'])->toBeTrue();

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('bulk.receipts.delete'), [
            'receipt_ids' => [$receipt->id],
        ])
        ->assertRedirect();

    expect(Receipt::find($receipt->id))->toBeNull();
    expect(File::find($file->id))->toBeNull();
    expect($dedupe->checkDuplication($content, $user->id)['isDuplicate'])->toBeFalse();
});

it('does not treat a deleted document as a duplicate source', function () {
    $user = User::factory()->create();
    $content = 'same-content';
    $hash = hash('sha256', $content);
    $guid = (string) Str::uuid();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'doc.pdf',
        'fileExtension' => 'pdf',
        'fileType' => 'application/pdf',
        'fileSize' => 123,
        'status' => 'completed',
        'file_hash' => $hash,
        'file_type' => 'document',
    ]);

    $document = Document::create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'title' => 'Test Document',
        'document_type' => 'other',
    ]);

    // Create extractable entity record (required for deduplication check)
    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'document',
        'entity_id' => $document->id,
        'is_primary' => true,
        'extracted_at' => now(),
    ]);

    $fullPath = 'documents/'.$user->id.'/'.$guid.'/original.pdf';
    Storage::disk('paperpulse')->put($fullPath, $content);

    $dedupe = app(FileDuplicationService::class);
    expect($dedupe->checkDuplication($content, $user->id)['isDuplicate'])->toBeTrue();

    $this->actingAs($user)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents.index'));

    Storage::disk('paperpulse')->assertMissing($fullPath);
    expect(Document::find($document->id))->toBeNull();
    expect(File::find($file->id))->toBeNull();
    expect($dedupe->checkDuplication($content, $user->id)['isDuplicate'])->toBeFalse();
});
