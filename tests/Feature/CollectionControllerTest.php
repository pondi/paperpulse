<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\File;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Collection Web Controller', function () {
    test('index page returns ok', function () {
        Collection::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get('/collections');

        $response->assertOk();
    });

    test('all endpoint returns active collections as json', function () {
        Collection::factory()->count(3)->create(['user_id' => $this->user->id, 'is_archived' => false]);
        Collection::factory()->archived()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/collections/all');

        $response->assertOk();
        $response->assertJsonCount(3);
    });

    test('can create a collection', function () {
        $response = $this->actingAs($this->user)->post('/collections', [
            'name' => 'My New Collection',
            'description' => 'A test collection',
            'icon' => 'briefcase',
            'color' => '#EF4444',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('collections', [
            'user_id' => $this->user->id,
            'name' => 'My New Collection',
            'icon' => 'briefcase',
        ]);
    });

    test('can view own collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get("/collections/{$collection->id}");

        $response->assertOk();
    });

    test('cannot view another users collection', function () {
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get("/collections/{$collection->id}");

        // Returns 403 due to policy authorization
        $response->assertForbidden();
    });

    test('can view shared collection', function () {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        CollectionShare::factory()->create([
            'collection_id' => $collection->id,
            'shared_by_user_id' => $owner->id,
            'shared_with_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/collections/{$collection->id}");

        $response->assertOk();
    });

    test('can update own collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->patch("/collections/{$collection->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect($collection->fresh()->name)->toBe('Updated Name');
    });

    test('cannot update collection without edit permission', function () {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        CollectionShare::factory()->create([
            'collection_id' => $collection->id,
            'shared_by_user_id' => $owner->id,
            'shared_with_user_id' => $this->user->id,
            'permission' => 'view',
        ]);

        $response = $this->actingAs($this->user)->patch("/collections/{$collection->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    });

    test('can delete own collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete("/collections/{$collection->id}");

        $response->assertRedirect('/collections');
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('collections', ['id' => $collection->id]);
    });

    test('cannot delete another users collection', function () {
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->delete("/collections/{$collection->id}");

        $response->assertForbidden();
    });

    test('can archive own collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id, 'is_archived' => false]);

        $response = $this->actingAs($this->user)->post("/collections/{$collection->id}/archive");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect($collection->fresh()->is_archived)->toBeTrue();
    });

    test('can unarchive own collection', function () {
        $collection = Collection::factory()->archived()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post("/collections/{$collection->id}/unarchive");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect($collection->fresh()->is_archived)->toBeFalse();
    });

    test('can add files to collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);
        $files = File::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post("/collections/{$collection->id}/files", [
            'file_ids' => $files->pluck('id')->toArray(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect($collection->fresh()->files)->toHaveCount(2);
    });

    test('can remove files from collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);
        $files = File::factory()->count(3)->create(['user_id' => $this->user->id]);
        $collection->files()->attach($files->pluck('id'));

        $response = $this->actingAs($this->user)->delete("/collections/{$collection->id}/files", [
            'file_ids' => [$files[0]->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect($collection->fresh()->files)->toHaveCount(2);
    });

    test('can share collection with another user', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->user)->post("/collections/{$collection->id}/share", [
            'email' => $targetUser->email,
            'permission' => 'view',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('collection_shares', [
            'collection_id' => $collection->id,
            'shared_with_user_id' => $targetUser->id,
        ]);
    });

    test('cannot share collection with self', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post("/collections/{$collection->id}/share", [
            'email' => $this->user->email,
            'permission' => 'view',
        ]);

        $response->assertSessionHasErrors('email');
    });

    test('can unshare collection', function () {
        $collection = Collection::factory()->create(['user_id' => $this->user->id]);
        $targetUser = User::factory()->create();
        CollectionShare::factory()->create([
            'collection_id' => $collection->id,
            'shared_by_user_id' => $this->user->id,
            'shared_with_user_id' => $targetUser->id,
        ]);

        $response = $this->actingAs($this->user)->delete("/collections/{$collection->id}/share/{$targetUser->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('collection_shares', [
            'collection_id' => $collection->id,
            'shared_with_user_id' => $targetUser->id,
        ]);
    });

    test('shared page returns ok', function () {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        CollectionShare::factory()->create([
            'collection_id' => $collection->id,
            'shared_by_user_id' => $owner->id,
            'shared_with_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/collections/shared');

        $response->assertOk();
    });
});
