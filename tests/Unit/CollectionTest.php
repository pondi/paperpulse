<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('collection can be created with factory', function () {
    $collection = Collection::factory()->create();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->name)->not->toBeEmpty()
        ->and($collection->slug)->not->toBeEmpty()
        ->and($collection->icon)->not->toBeEmpty()
        ->and($collection->color)->not->toBeEmpty()
        ->and($collection->is_archived)->toBeFalse();
});

test('collection generates unique slug per user', function () {
    $user = User::factory()->create();

    $collection1 = Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Collection',
    ]);

    $collection2 = Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Collection',
    ]);

    expect($collection1->slug)->toBe('test-collection')
        ->and($collection2->slug)->toBe('test-collection-1');
});

test('different users can have same collection slug', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $collection1 = Collection::factory()->create([
        'user_id' => $user1->id,
        'name' => 'My Collection',
    ]);

    $collection2 = Collection::factory()->create([
        'user_id' => $user2->id,
        'name' => 'My Collection',
    ]);

    expect($collection1->slug)->toBe('my-collection')
        ->and($collection2->slug)->toBe('my-collection');
});

test('collection updates slug when name changes', function () {
    $collection = Collection::factory()->create([
        'name' => 'Original Name',
    ]);

    expect($collection->slug)->toBe('original-name');

    $collection->update(['name' => 'New Name']);

    expect($collection->fresh()->slug)->toBe('new-name');
});

test('collection can be archived', function () {
    $collection = Collection::factory()->create();

    expect($collection->is_archived)->toBeFalse();

    $collection->update(['is_archived' => true]);

    expect($collection->fresh()->is_archived)->toBeTrue();
});

test('collection factory archived state works', function () {
    $collection = Collection::factory()->archived()->create();

    expect($collection->is_archived)->toBeTrue();
});

test('collection belongs to user', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $user->id]);

    expect($collection->user->id)->toBe($user->id);
});

test('collection can have files', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $user->id]);
    $file = File::factory()->create(['user_id' => $user->id]);

    $collection->files()->attach($file);

    expect($collection->files)->toHaveCount(1)
        ->and($collection->files->first()->id)->toBe($file->id);
});

test('file can belong to multiple collections', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $collection1 = Collection::factory()->create(['user_id' => $user->id]);
    $collection2 = Collection::factory()->create(['user_id' => $user->id]);

    $file->collections()->attach([$collection1->id, $collection2->id]);

    expect($file->collections)->toHaveCount(2);
});

test('collection files count attribute works', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $user->id]);
    $files = File::factory()->count(3)->create(['user_id' => $user->id]);

    $collection->files()->attach($files->pluck('id'));

    expect($collection->fresh()->files_count)->toBe(3);
});

test('collection active scope returns non-archived collections', function () {
    $user = User::factory()->create();

    Collection::factory()->count(3)->create([
        'user_id' => $user->id,
        'is_archived' => false,
    ]);

    Collection::factory()->count(2)->archived()->create([
        'user_id' => $user->id,
    ]);

    $activeCollections = Collection::where('user_id', $user->id)->active()->get();

    expect($activeCollections)->toHaveCount(3);
});

test('collection archived scope returns archived collections', function () {
    $user = User::factory()->create();

    Collection::factory()->count(3)->create([
        'user_id' => $user->id,
        'is_archived' => false,
    ]);

    Collection::factory()->count(2)->archived()->create([
        'user_id' => $user->id,
    ]);

    $archivedCollections = Collection::where('user_id', $user->id)->archived()->get();

    expect($archivedCollections)->toHaveCount(2);
});

test('collection search scope searches by name', function () {
    $user = User::factory()->create();

    Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Kitchen Renovation',
    ]);

    Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Tax Documents',
    ]);

    $results = Collection::where('user_id', $user->id)->search('kitchen')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Kitchen Renovation');
});

test('collection search scope searches by description', function () {
    $user = User::factory()->create();

    Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Renovation',
        'description' => 'All kitchen related receipts',
    ]);

    Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Taxes',
        'description' => 'Tax documents for 2024',
    ]);

    $results = Collection::where('user_id', $user->id)->search('kitchen')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Renovation');
});

test('find or create by name creates new collection', function () {
    $user = User::factory()->create();

    $collection = Collection::findOrCreateByName('New Collection', $user->id);

    expect($collection->name)->toBe('New Collection')
        ->and($collection->user_id)->toBe($user->id)
        ->and($collection->icon)->toBe('folder');
});

test('find or create by name returns existing collection', function () {
    $user = User::factory()->create();
    $existing = Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Existing Collection',
    ]);

    $collection = Collection::findOrCreateByName('Existing Collection', $user->id);

    expect($collection->id)->toBe($existing->id);
});

test('collection icons constant contains valid icons', function () {
    expect(Collection::ICONS)
        ->toBeArray()
        ->toContain('folder')
        ->toContain('document')
        ->toContain('briefcase');
});

test('collection colors constant contains valid hex colors', function () {
    expect(Collection::COLORS)
        ->toBeArray()
        ->each(fn ($color) => $color->toMatch('/^#[0-9A-Fa-f]{6}$/'));
});

test('collection generates random color from colors constant', function () {
    $collection = Collection::factory()->create(['color' => null]);

    expect(Collection::COLORS)->toContain($collection->color);
});

test('collection share can be created', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    $share = CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect($share->collection_id)->toBe($collection->id)
        ->and($share->share_token)->not->toBeEmpty()
        ->and($share->permission)->toBe('view');
});

test('collection share generates unique token', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    $share = CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect($share->share_token)->toHaveLength(32);
});

test('collection is shared with returns true for shared user', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect($collection->isSharedWith($targetUser))->toBeTrue()
        ->and($collection->isSharedWith($owner))->toBeFalse();
});

test('collection is shared with respects expiration', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
        'expires_at' => now()->subDay(),
    ]);

    expect($collection->isSharedWith($targetUser))->toBeFalse();
});

test('collection can be viewed by owner', function () {
    $owner = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    expect($collection->canBeViewedBy($owner))->toBeTrue();
});

test('collection can be viewed by shared user', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect($collection->canBeViewedBy($targetUser))->toBeTrue();
});

test('collection cannot be viewed by non-shared user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    expect($collection->canBeViewedBy($otherUser))->toBeFalse();
});

test('collection can be edited by owner', function () {
    $owner = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    expect($collection->canBeEditedBy($owner))->toBeTrue();
});

test('collection can be edited by user with edit permission', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'edit',
    ]);

    expect($collection->canBeEditedBy($targetUser))->toBeTrue();
});

test('collection cannot be edited by user with view permission', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect($collection->canBeEditedBy($targetUser))->toBeFalse();
});

test('collection share active scope filters expired shares', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
        'expires_at' => now()->subDay(),
    ]);

    $activeShares = CollectionShare::where('collection_id', $collection->id)->active()->get();

    expect($activeShares)->toHaveCount(0);
});

test('deleting collection removes pivot entries', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $user->id]);
    $file = File::factory()->create(['user_id' => $user->id]);

    $collection->files()->attach($file);

    expect($file->collections)->toHaveCount(1);

    $collection->delete();

    expect($file->fresh()->collections)->toHaveCount(0);
});

test('deleting collection removes shares', function () {
    $owner = User::factory()->create();
    $targetUser = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);

    CollectionShare::create([
        'collection_id' => $collection->id,
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $targetUser->id,
        'permission' => 'view',
    ]);

    expect(CollectionShare::where('collection_id', $collection->id)->count())->toBe(1);

    // Soft delete the collection - shares remain but become inaccessible
    $collection->delete();

    // Shares still exist in the database (will be cleaned up during permanent deletion)
    expect(CollectionShare::where('collection_id', $collection->id)->count())->toBe(1);

    // Permanent deletion removes shares via cascade
    $collection->forceDelete();

    expect(CollectionShare::where('collection_id', $collection->id)->count())->toBe(0);
});
