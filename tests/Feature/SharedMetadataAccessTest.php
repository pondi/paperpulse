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
