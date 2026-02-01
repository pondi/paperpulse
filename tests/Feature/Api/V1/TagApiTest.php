<?php

use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('list tags', function () {
    it('lists all tags for the authenticated user', function () {
        Tag::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.tags.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    });

    it('does not show other users tags', function () {
        $otherUser = User::factory()->create();

        Tag::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        Tag::factory()->count(5)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson(route('api.tags.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    });

    it('can search tags by name', function () {
        Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Business',
        ]);

        Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Personal',
        ]);

        $response = $this->getJson(route('api.tags.index', ['search' => 'bus']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Business');
    });

    it('respects per_page parameter', function () {
        Tag::factory()->count(20)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.tags.index', ['per_page' => 5]));

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        expect($response->json('pagination.per_page'))->toBe(5);
    });
});

describe('create tag', function () {
    it('creates a new tag', function () {
        $response = $this->postJson(route('api.tags.store'), [
            'name' => 'Work',
            'color' => '#3B82F6',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Work');
        $response->assertJsonPath('data.color', '#3B82F6');

        $this->assertDatabaseHas('tags', [
            'user_id' => $this->user->id,
            'name' => 'Work',
        ]);
    });

    it('generates a slug when creating a tag', function () {
        $response = $this->postJson(route('api.tags.store'), [
            'name' => 'My Work Tag',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'my-work-tag');
    });

    it('generates a random color when not provided', function () {
        $response = $this->postJson(route('api.tags.store'), [
            'name' => 'Random Color Tag',
        ]);

        $response->assertStatus(201);
        expect($response->json('data.color'))->not->toBeNull();
    });

    it('prevents duplicate tag names for the same user', function () {
        Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Duplicate',
        ]);

        $response = $this->postJson(route('api.tags.store'), [
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('errors.name.0', 'A tag with this name already exists.');
    });

    it('validates name is required', function () {
        $response = $this->postJson(route('api.tags.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    });
});

describe('update tag', function () {
    it('updates an existing tag', function () {
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original',
        ]);

        $response = $this->patchJson(route('api.tags.update', $tag), [
            'name' => 'Updated',
            'color' => '#EF4444',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated');
        $response->assertJsonPath('data.color', '#EF4444');
    });

    it('updates the slug when name changes', function () {
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->patchJson(route('api.tags.update', $tag), [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', 'new-name');
    });

    it('prevents updating to a duplicate name', function () {
        Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing',
        ]);

        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Different',
        ]);

        $response = $this->patchJson(route('api.tags.update', $tag), [
            'name' => 'Existing',
        ]);

        $response->assertStatus(409);
    });

    it('cannot update another users tag', function () {
        $otherUser = User::factory()->create();
        $tag = Tag::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Tag',
        ]);

        $response = $this->patchJson(route('api.tags.update', $tag), [
            'name' => 'Hacked',
        ]);

        // Returns 404 because BelongsToUser trait scopes queries by user_id
        $response->assertStatus(404);
    });
});

describe('delete tag', function () {
    it('deletes a tag', function () {
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'To Delete',
        ]);

        $response = $this->deleteJson(route('api.tags.destroy', $tag));

        $response->assertStatus(200);
        $this->assertSoftDeleted('tags', ['id' => $tag->id]);
    });

    it('cannot delete another users tag', function () {
        $otherUser = User::factory()->create();
        $tag = Tag::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Tag',
        ]);

        $response = $this->deleteJson(route('api.tags.destroy', $tag));

        // Returns 404 because BelongsToUser trait scopes queries by user_id
        $response->assertStatus(404);
    });
});

describe('authentication', function () {
    it('requires authentication to list tags', function () {
        // Don't authenticate - test as guest
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.tags.index'));

        $response->assertStatus(401);
    });

    it('requires authentication to create tags', function () {
        // Don't authenticate - test as guest
        $this->app['auth']->forgetGuards();

        $response = $this->postJson(route('api.tags.store'), [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    });
});
