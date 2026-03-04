<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Auth ---

it('requires authentication for tag index', function () {
    $this->get(route('tags.index'))
        ->assertRedirect(route('login'));
});

// --- Index ---

it('lists tags for authenticated user', function () {
    $user = User::factory()->create();
    Tag::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tags/Index')
            ->has('tags.data', 3)
        );
});

it('does not show other users tags', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Tag::factory()->count(5)->create(['user_id' => $other->id]);
    Tag::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tags.data', 2));
});

// --- All (JSON) ---

it('returns all tags as JSON', function () {
    $user = User::factory()->create();
    Tag::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('tags.all'))
        ->assertOk()
        ->assertJsonCount(3);
});

// --- Store ---

it('creates a tag with valid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tags.store'), [
            'name' => 'Important',
            'color' => '#EF4444',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tags', [
        'user_id' => $user->id,
        'name' => 'Important',
        'color' => '#EF4444',
    ]);
});

it('rejects tag without name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tags.store'), ['color' => '#EF4444'])
        ->assertSessionHasErrors('name');
});

it('rejects tag with invalid color format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tags.store'), [
            'name' => 'Test',
            'color' => 'invalid',
        ])
        ->assertSessionHasErrors('color');
});

// --- Update ---

it('updates an existing tag', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

    $this->actingAs($user)
        ->patch(route('tags.update', $tag), [
            'name' => 'Renamed',
            'color' => '#3B82F6',
        ])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('Renamed');
});

it('prevents updating another users tag', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $tag = Tag::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('tags.update', $tag), ['name' => 'Stolen'])
        ->assertNotFound();
});

// --- Destroy ---

it('deletes a tag', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('tags.destroy', $tag))
        ->assertRedirect();

    $this->assertSoftDeleted('tags', ['id' => $tag->id]);
});

it('prevents deleting another users tag', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $tag = Tag::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('tags.destroy', $tag))
        ->assertNotFound();
});

// --- Merge ---

it('merges one tag into another', function () {
    $user = User::factory()->create();
    $sourceTag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Source']);
    $targetTag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Target']);

    $file = File::factory()->create(['user_id' => $user->id]);
    $sourceTag->files()->attach($file->id);

    $this->actingAs($user)
        ->post(route('tags.merge', $sourceTag), [
            'target_tag_id' => $targetTag->id,
        ])
        ->assertRedirect();

    // Source tag soft deleted
    $this->assertSoftDeleted('tags', ['id' => $sourceTag->id]);
    // File moved to target tag
    expect($targetTag->files()->where('files.id', $file->id)->exists())->toBeTrue();
});

it('prevents merging a tag into itself', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('tags.merge', $tag), [
            'target_tag_id' => $tag->id,
        ])
        ->assertSessionHasErrors('target_tag_id');
});
