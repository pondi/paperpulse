<?php

use App\Models\Collection;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;

it('rejects unknown workspace actions without mutating files', function (): void {
    $file = File::factory()->create(['status' => 'completed']);
    $this->actingAs($file->user)->postJson(route('workspace.actions'), ['action' => 'unsupported', 'file_ids' => [$file->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($file->fresh()->meta)->not->toHaveKey('workspace_review');
});

it('saves notes descriptions and staged folders through owned metadata', function (string $model, string $type): void {
    $file = File::factory()->create(['status' => 'completed', 'note' => 'Original note']);
    $entity = $model::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $old = Collection::factory()->create(['user_id' => $file->user_id]);
    $new = Collection::factory()->create(['user_id' => $file->user_id]);
    $shared = Collection::factory()->create();
    $file->collections()->attach([$old->id, $shared->id]);
    $data = [
        'action' => 'save', 'file_ids' => [$file->id], 'entity_type' => $type, 'entity_id' => $entity->id,
        'title' => 'Updated title', 'total_amount' => 42, 'receipt_date' => '2026-10-05', 'currency' => 'NOK',
        'receipt_description' => 'Updated description', 'note' => "First line\nSecond line", 'collection_ids' => [$new->id],
    ];
    $this->actingAs($file->user)->post(route('workspace.actions'), $data)->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->note)->toBe("First line\nSecond line")
        ->and($file->collections()->withoutGlobalScope('user')->get()->modelKeys())->toEqualCanonicalizing([$new->id, $shared->id]);
    if ($type === 'receipt') {
        expect($entity->fresh()->receipt_description)->toBe('Updated description');
    }
    $this->post(route('workspace.actions'), [...$data, 'collection_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
    expect($file->collections()->withoutGlobalScope('user')->get()->modelKeys())->toBe([$shared->id]);
})->with([[Receipt::class, 'receipt'], [Document::class, 'document']]);

it('rejects invalid metadata edits without changing notes or folders', function (string $field): void {
    $file = File::factory()->create(['status' => 'completed', 'note' => 'Original note']);
    $document = Document::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id, 'title' => 'Original title', 'note' => 'Original note']);
    $folder = Collection::factory()->create(['user_id' => $file->user_id]);
    $file->collections()->attach($folder);
    $value = $field === 'collection_ids' ? [Collection::factory()->create()->id] : str_repeat('x', 1001);
    $this->actingAs($file->user)->postJson(route('workspace.actions'), [
        'action' => 'save', 'file_ids' => [$file->id], 'entity_type' => 'document', 'entity_id' => $document->id,
        'title' => 'Changed title', $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors($field === 'collection_ids' ? 'collection_ids.0' : $field);
    expect($file->fresh()->note)->toBe('Original note')
        ->and($document->fresh()->title)->toBe('Original title')
        ->and($file->collections()->withoutGlobalScope('user')->get()->modelKeys())->toBe([$folder->id]);
})->with(['note', 'receipt_description', 'collection_ids']);

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
