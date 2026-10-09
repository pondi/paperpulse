<?php

use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;

it('saves owned document details and clears the previous approval', function (): void {
    $file = File::factory()->create(['status' => 'completed', 'meta' => ['workspace_review' => ['status' => 'approved']]]);
    $document = Document::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $this->actingAs($file->user)->from(route('library.index'))->post(route('workspace.actions'), [
        'action' => 'save', 'file_ids' => [$file->id], 'entity_type' => 'document', 'entity_id' => $document->id,
        'title' => 'Updated agreement', 'summary' => 'An accurate summary.',
    ])->assertRedirectToRoute('library.index')->assertSessionHasNoErrors();
    expect($document->fresh()->title)->toBe('Updated agreement')
        ->and($document->fresh()->summary)->toBe('An accurate summary.')
        ->and($file->fresh()->meta)->not->toHaveKey('workspace_review');
});

it('approves reconciled receipt totals through the workspace', function (): void {
    $file = File::factory()->create(['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'receipt_totals']]]);
    $receipt = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id, 'total_amount' => 10, 'tax_amount' => 0]);
    $receipt->lineItems()->create(['text' => 'Service', 'qty' => 1, 'price' => 10, 'total' => 10]);
    $this->actingAs($file->user)->post(route('workspace.actions'), ['action' => 'approve', 'file_ids' => [$file->id]])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->status)->toBe('completed')
        ->and($file->fresh()->meta['workspace_review']['status'])->toBe('approved')
        ->and($file->fresh()->meta['workspace_review']['reviewed_by'])->toBe($file->user_id);
});

it('rejects unsafe batch approvals without changing any selected file', function (string $status, ?string $reason): void {
    $ready = File::factory()->create(['status' => 'completed']);
    $blocked = File::factory()->create(['user_id' => $ready->user_id, 'status' => $status, 'meta' => ['review' => ['reason' => $reason]]]);
    $this->actingAs($ready->user)->postJson(route('workspace.actions'), ['action' => 'approve', 'file_ids' => [$ready->id, $blocked->id]])
        ->assertUnprocessable();
    expect($ready->fresh()->meta)->not->toHaveKey('workspace_review')
        ->and($blocked->fresh()->status)->toBe($status);
})->with([
    ['processing', null], ['failed', null], ['needs_review', 'uncertain_classification'],
    ['needs_review', 'processing_limit'], ['needs_review', 'receipt_totals'],
]);

it('rejects mutations of a shared file even when submitted directly', function (string $action): void {
    $file = File::factory()->create(['status' => 'completed']);
    $viewer = User::factory()->create();
    FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $file->user_id,
        'shared_with_user_id' => $viewer->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->actingAs($viewer)->postJson(route('workspace.actions'), ['action' => $action, 'file_ids' => [$file->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('file_ids.0');
    expect($file->fresh())->not->toBeNull()->and($file->fresh()->meta)->not->toHaveKey('workspace_review');
})->with(['approve', 'flag', 'delete']);

it('cannot save an unrelated extracted record through a selected file', function (): void {
    $file = File::factory()->create(['status' => 'completed']);
    $document = Document::factory()->create(['user_id' => $file->user_id, 'title' => 'Original title']);
    $this->actingAs($file->user)->postJson(route('workspace.actions'), [
        'action' => 'save', 'file_ids' => [$file->id], 'entity_type' => 'document', 'entity_id' => $document->id, 'title' => 'Wrong file',
    ])->assertNotFound();
    expect($document->fresh()->title)->toBe('Original title');
});
