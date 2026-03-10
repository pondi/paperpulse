<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Auth ---

it('requires authentication for category index', function () {
    $this->get(route('categories.index'))
        ->assertRedirect(route('login'));
});

// --- Index ---

it('lists categories for authenticated user', function () {
    $user = User::factory()->create();

    Category::create([
        'user_id' => $user->id,
        'name' => 'Groceries',
        'slug' => 'groceries',
        'color' => '#10B981',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Categories/Index')
            ->has('categories', 1)
        );
});

it('returns empty categories for user with none', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories', 0));
});

// --- Store ---

it('creates a category with valid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Electronics',
            'color' => '#3B82F6',
            'description' => 'Electronic purchases',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Electronics',
        'color' => '#3B82F6',
    ]);
});

it('rejects category without name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'color' => '#3B82F6',
        ])
        ->assertSessionHasErrors('name');
});

it('rejects invalid hex color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Test',
            'color' => 'not-a-color',
        ])
        ->assertSessionHasErrors('color');
});

// --- Update ---

it('updates an existing category', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
        'color' => '#000000',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'New Name',
            'color' => '#FF5733',
        ])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('New Name');
});

it('prevents updating another users category', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::create([
        'user_id' => $owner->id,
        'name' => 'Private',
        'slug' => 'private',
        'sort_order' => 0,
    ]);

    $this->actingAs($intruder)
        ->patch(route('categories.update', $category), ['name' => 'Stolen'])
        ->assertNotFound();
});

// --- Destroy ---

it('deletes a category with no items', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Empty',
        'slug' => 'empty',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect();

    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

it('prevents deleting another users category', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::create([
        'user_id' => $owner->id,
        'name' => 'Protected',
        'slug' => 'protected',
        'sort_order' => 0,
    ]);

    $this->actingAs($intruder)
        ->delete(route('categories.destroy', $category))
        ->assertNotFound();
});

// --- Update Order ---

it('updates sort order for owned categories', function () {
    $user = User::factory()->create();

    $catA = Category::create([
        'user_id' => $user->id,
        'name' => 'A',
        'slug' => 'a',
        'sort_order' => 0,
    ]);

    $catB = Category::create([
        'user_id' => $user->id,
        'name' => 'B',
        'slug' => 'b',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('categories.order'), [
            'categories' => [
                ['id' => $catA->id, 'sort_order' => 1],
                ['id' => $catB->id, 'sort_order' => 0],
            ],
        ])
        ->assertOk();

    expect($catA->fresh()->sort_order)->toBe(1);
    expect($catB->fresh()->sort_order)->toBe(0);
});

// --- Create Defaults ---

it('creates default categories for new user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.defaults'))
        ->assertRedirect();

    expect(Category::where('user_id', $user->id)->count())->toBeGreaterThan(10);
});

it('does not create defaults if user already has categories', function () {
    $user = User::factory()->create();

    Category::create([
        'user_id' => $user->id,
        'name' => 'Existing',
        'slug' => 'existing',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('categories.defaults'))
        ->assertRedirect();

    // Should still have just 1 (no defaults added)
    expect(Category::where('user_id', $user->id)->count())->toBe(1);
});
