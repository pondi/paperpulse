<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\User;
use App\Services\CollectionSharingService;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->withoutVite());

test('shared collection metadata is visible only for active authorized recipients', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $stranger = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->create(['user_id' => $owner->id, 'title' => 'Authorized metadata']);
    $collection->files()->attach($document->file_id);
    ExtractableEntity::create(['file_id' => $document->file_id, 'user_id' => $owner->id,
        'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
    $share = CollectionShare::create(['collection_id' => $collection->id, 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->actingAs($recipient);
    expect(app(CollectionSharingService::class)->getSharedWithUser($recipient)->modelKeys())->toBe([$collection->id]);
    $this->get('/collections/'.$collection->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('collection.files', 1)->where('collection.files.0.primary_entity.entity.title', 'Authorized metadata'));
    $this->actingAs($stranger)->get('/collections/'.$collection->id)->assertForbidden();
    $share->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($recipient)->get('/collections/'.$collection->id)->assertForbidden();
    expect(app(CollectionSharingService::class)->getSharedWithUser($recipient))->toHaveCount(0);
    $share->update(['expires_at' => null]);
    $collection->delete();
    expect(app(CollectionSharingService::class)->getSharedWithUser($recipient))->toHaveCount(0);
    $this->get('/collections/'.$collection->id)->assertNotFound();
});


test('mixed shared lists return actual active entities with a consistent shape', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $owner->id, 'file_id' => \App\Models\File::factory()->create(['user_id' => $owner->id])->id]);
    $receipt = \App\Models\Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => \App\Models\File::factory()->create(['user_id' => $owner->id])->id]);
    foreach ([[$receipt, 'receipt', null], [$document, 'document', null]] as [$entity, $type, $expires]) {
        \App\Models\FileShare::create(['file_id' => $entity->file_id, 'file_type' => $type,
            'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $recipient->id,
            'permission' => 'view', 'shared_at' => now(), 'expires_at' => $expires]);
    }
    $expired = Document::factory()->create(['user_id' => $owner->id, 'file_id' => \App\Models\File::factory()->create(['user_id' => $owner->id])->id]);
    \App\Models\FileShare::create(['file_id' => $expired->file_id, 'file_type' => 'document',
        'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $recipient->id,
        'permission' => 'view', 'shared_at' => now(), 'expires_at' => now()->subMinute()]);
    $this->actingAs($recipient);
    $items = app(\App\Services\SharingService::class)->getSharedWithUser($recipient);
    expect($items)->toHaveCount(2);
    expect($items->pluck('file')->map(fn ($entity) => get_class($entity))->all())
        ->toEqualCanonicalizing([Document::class, \App\Models\Receipt::class]);
    foreach ($items as $item) {
        expect(array_keys($item))->toBe(['share', 'file', 'shared_by', 'permission', 'expires_at']);
    }
    $document->delete();
    expect(app(\App\Services\SharingService::class)->getSharedWithUser($recipient))->toHaveCount(1);
});


test('authorized receipt metadata retains merchant names for recipients', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $merchant = \App\Models\Merchant::create(['user_id' => $owner->id, 'name' => 'Visible merchant']);
    $file = \App\Models\File::factory()->create(['user_id' => $owner->id]);
    $receipt = \App\Models\Receipt::factory()->create(['file_id' => $file->id, 'user_id' => $owner->id, 'merchant_id' => $merchant->id]);
    \App\Models\FileShare::create(['file_id' => $file->id, 'file_type' => 'receipt',
        'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->actingAs($recipient)->get('/receipts/'.$receipt->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('receipt.merchant.name', 'Visible merchant'));
});
