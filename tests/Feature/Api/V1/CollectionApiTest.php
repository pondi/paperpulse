<?php

use App\Models\Collection;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('list collections', function () {
    it('lists all collections for the authenticated user', function () {
        Collection::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.collections.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    });

    it('does not show other users collections', function () {
        $otherUser = User::factory()->create();

        Collection::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        Collection::factory()->count(5)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson(route('api.collections.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    });

    it('can search collections by name', function () {
        Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Work Documents',
        ]);

        Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Personal Files',
        ]);

        $response = $this->getJson(route('api.collections.index', ['search' => 'work']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Work Documents');
    });

    it('can filter by archived status', function () {
        Collection::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_archived' => false,
        ]);

        Collection::factory()->create([
            'user_id' => $this->user->id,
            'is_archived' => true,
        ]);

        $response = $this->getJson(route('api.collections.index', ['archived' => true]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.is_archived', true);
    });

    it('respects per_page parameter', function () {
        Collection::factory()->count(20)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.collections.index', ['per_page' => 5]));

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        expect($response->json('pagination.per_page'))->toBe(5);
    });

    it('includes files_count in response', function () {
        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.collections.index'));

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.files_count', 0);
    });
});

describe('create collection', function () {
    it('creates a new collection', function () {
        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Tax Documents',
            'description' => 'All tax related documents',
            'icon' => 'folder',
            'color' => '#3B82F6',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Tax Documents');
        $response->assertJsonPath('data.description', 'All tax related documents');
        $response->assertJsonPath('data.icon', 'folder');
        $response->assertJsonPath('data.color', '#3B82F6');

        $this->assertDatabaseHas('collections', [
            'user_id' => $this->user->id,
            'name' => 'Tax Documents',
        ]);
    });

    it('generates a slug when creating a collection', function () {
        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'My Work Collection',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'my-work-collection');
    });

    it('uses default icon when not provided', function () {
        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Default Icon Collection',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.icon', 'folder');
    });

    it('generates a random color when not provided', function () {
        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Random Color Collection',
        ]);

        $response->assertStatus(201);
        expect($response->json('data.color'))->not->toBeNull();
    });

    it('prevents duplicate collection names for the same user', function () {
        Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Duplicate',
        ]);

        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('errors.name.0', 'A collection with this name already exists.');
    });

    it('validates name is required', function () {
        $response = $this->postJson(route('api.collections.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    });

    it('validates icon is from allowed list', function () {
        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Test Collection',
            'icon' => 'invalid-icon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['icon']);
    });
});

describe('update collection', function () {
    it('updates an existing collection', function () {
        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original',
        ]);

        $response = $this->patchJson(route('api.collections.update', $collection), [
            'name' => 'Updated',
            'description' => 'New description',
            'color' => '#EF4444',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated');
        $response->assertJsonPath('data.description', 'New description');
        $response->assertJsonPath('data.color', '#EF4444');
    });

    it('updates the slug when name changes', function () {
        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->patchJson(route('api.collections.update', $collection), [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', 'new-name');
    });

    it('can archive a collection', function () {
        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'is_archived' => false,
        ]);

        $response = $this->patchJson(route('api.collections.update', $collection), [
            'is_archived' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_archived', true);
    });

    it('prevents updating to a duplicate name', function () {
        Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing',
        ]);

        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Different',
        ]);

        $response = $this->patchJson(route('api.collections.update', $collection), [
            'name' => 'Existing',
        ]);

        $response->assertStatus(409);
    });

    it('cannot update another users collection', function () {
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Collection',
        ]);

        $response = $this->patchJson(route('api.collections.update', $collection), [
            'name' => 'Hacked',
        ]);

        // Returns 403 because ownership check in controller denies access
        $response->assertStatus(403);
    });
});

describe('delete collection', function () {
    it('deletes a collection', function () {
        $collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'To Delete',
        ]);

        $response = $this->deleteJson(route('api.collections.destroy', $collection));

        $response->assertStatus(200);
        $this->assertSoftDeleted('collections', ['id' => $collection->id]);
    });

    it('cannot delete another users collection', function () {
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Collection',
        ]);

        $response = $this->deleteJson(route('api.collections.destroy', $collection));

        // Returns 403 because ownership check in controller denies access
        $response->assertStatus(403);
    });
});

describe('authentication', function () {
    it('requires authentication to list collections', function () {
        // Don't authenticate - test as guest
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.collections.index'));

        $response->assertStatus(401);
    });

    it('requires authentication to create collections', function () {
        // Don't authenticate - test as guest
        $this->app['auth']->forgetGuards();

        $response = $this->postJson(route('api.collections.store'), [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    });
});
