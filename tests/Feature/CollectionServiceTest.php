<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\File;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\CollectionSharingService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->collectionService = app(CollectionService::class);
    $this->sharingService = app(CollectionSharingService::class);
});

describe('CollectionService', function () {
    test('can create a collection', function () {
        $user = User::factory()->create();

        $collection = $this->collectionService->create([
            'name' => 'My Collection',
            'description' => 'Test description',
            'icon' => 'briefcase',
            'color' => '#EF4444',
        ], $user->id);

        expect($collection)
            ->toBeInstanceOf(Collection::class)
            ->name->toBe('My Collection')
            ->description->toBe('Test description')
            ->icon->toBe('briefcase')
            ->color->toBe('#EF4444')
            ->user_id->toBe($user->id);
    });

    test('can update a collection', function () {
        $collection = Collection::factory()->create();

        $updated = $this->collectionService->update($collection, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        expect($updated)
            ->name->toBe('Updated Name')
            ->description->toBe('Updated description');
    });

    test('can delete a collection', function () {
        $collection = Collection::factory()->create();
        $id = $collection->id;

        $result = $this->collectionService->delete($collection);

        expect($result)->toBeTrue()
            ->and(Collection::find($id))->toBeNull();
    });

    test('can archive a collection', function () {
        $collection = Collection::factory()->create(['is_archived' => false]);

        $archived = $this->collectionService->archive($collection);

        expect($archived->is_archived)->toBeTrue();
    });

    test('can unarchive a collection', function () {
        $collection = Collection::factory()->archived()->create();

        $unarchived = $this->collectionService->unarchive($collection);

        expect($unarchived->is_archived)->toBeFalse();
    });

    test('can add files to collection', function () {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $files = File::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->collectionService->addFiles($collection, $files->pluck('id')->toArray());

        expect($collection->fresh()->files)->toHaveCount(3);
    });

    test('only adds files owned by user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $ownedFile = File::factory()->create(['user_id' => $user->id]);
        $otherFile = File::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $this->collectionService->addFiles($collection, [$ownedFile->id, $otherFile->id]);

        expect($collection->fresh()->files)->toHaveCount(1)
            ->and($collection->files->first()->id)->toBe($ownedFile->id);
    });

    test('can remove files from collection', function () {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $files = File::factory()->count(3)->create(['user_id' => $user->id]);

        $collection->files()->attach($files->pluck('id'));

        $this->collectionService->removeFiles($collection, [$files[0]->id, $files[1]->id]);

        expect($collection->fresh()->files)->toHaveCount(1);
    });

    test('can get active collections for selector', function () {
        $user = User::factory()->create();

        Collection::factory()->count(3)->create(['user_id' => $user->id, 'is_archived' => false]);
        Collection::factory()->count(2)->archived()->create(['user_id' => $user->id]);

        $activeCollections = $this->collectionService->getActiveCollectionsForSelector($user->id);

        expect($activeCollections)->toHaveCount(3);
    });

    test('can add file to multiple collections', function () {
        $user = User::factory()->create();
        $file = File::factory()->create(['user_id' => $user->id]);
        $collections = Collection::factory()->count(3)->create(['user_id' => $user->id]);

        $this->collectionService->addFileToCollections($file, $collections->pluck('id')->toArray());

        expect($file->fresh()->collections)->toHaveCount(3);
    });

    test('get files count returns correct count', function () {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $files = File::factory()->count(5)->create(['user_id' => $user->id]);

        $collection->files()->attach($files->pluck('id'));

        expect($this->collectionService->getFilesCount($collection))->toBe(5);
    });
});

describe('CollectionSharingService', function () {
    test('can share collection with another user', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        $share = $this->sharingService->shareCollection($collection, $targetUser);

        expect($share)
            ->toBeInstanceOf(CollectionShare::class)
            ->collection_id->toBe($collection->id)
            ->shared_with_user_id->toBe($targetUser->id)
            ->permission->toBe('view');
    });

    test('can share collection with edit permission', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        $share = $this->sharingService->shareCollection($collection, $targetUser, [
            'permission' => 'edit',
        ]);

        expect($share->permission)->toBe('edit');
    });

    test('cannot share collection you do not own', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser);

        expect(fn () => $this->sharingService->shareCollection($collection, $targetUser))
            ->toThrow(AuthorizationException::class);
    });

    test('cannot share collection with yourself', function () {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        expect(fn () => $this->sharingService->shareCollection($collection, $owner))
            ->toThrow(InvalidArgumentException::class);
    });

    test('user has access to owned collection', function () {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        expect($this->sharingService->userHasAccess($collection, $owner))->toBeTrue();
    });

    test('user has access to shared collection', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser);

        expect($this->sharingService->userHasAccess($collection, $targetUser))->toBeTrue();
    });

    test('user without share has no access', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        expect($this->sharingService->userHasAccess($collection, $otherUser))->toBeFalse();
    });

    test('view permission cannot edit', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser, ['permission' => 'view']);

        expect($this->sharingService->userHasAccess($collection, $targetUser, 'view'))->toBeTrue()
            ->and($this->sharingService->userHasAccess($collection, $targetUser, 'edit'))->toBeFalse();
    });

    test('edit permission can view and edit', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser, ['permission' => 'edit']);

        expect($this->sharingService->userHasAccess($collection, $targetUser, 'view'))->toBeTrue()
            ->and($this->sharingService->userHasAccess($collection, $targetUser, 'edit'))->toBeTrue();
    });

    test('can unshare collection', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser);

        expect($this->sharingService->userHasAccess($collection, $targetUser))->toBeTrue();

        $this->sharingService->unshare($collection, $targetUser);

        expect($this->sharingService->userHasAccess($collection, $targetUser))->toBeFalse();
    });

    test('expired share denies access', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser, [
            'expires_at' => now()->subDay(),
        ]);

        expect($this->sharingService->userHasAccess($collection, $targetUser))->toBeFalse();
    });

    test('user has transitive file access via shared collection', function () {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        $file = File::factory()->create(['user_id' => $owner->id]);

        $collection->files()->attach($file);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser);

        expect($this->sharingService->userHasTransitiveFileAccess($file->id, $targetUser))->toBeTrue();
    });

    test('user does not have transitive access without share', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        $file = File::factory()->create(['user_id' => $owner->id]);

        $collection->files()->attach($file);

        expect($this->sharingService->userHasTransitiveFileAccess($file->id, $otherUser))->toBeFalse();
    });

    test('cleanup expired shares removes only expired', function () {
        $owner = User::factory()->create();
        $targetUser1 = User::factory()->create();
        $targetUser2 = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        // Create expired share
        $this->sharingService->shareCollection($collection, $targetUser1, [
            'expires_at' => now()->subDay(),
        ]);

        // Create valid share
        $this->sharingService->shareCollection($collection, $targetUser2, [
            'expires_at' => now()->addDay(),
        ]);

        $deletedCount = $this->sharingService->cleanupExpiredShares();

        expect($deletedCount)->toBe(1)
            ->and(CollectionShare::count())->toBe(1);
    });

    test('get shares returns all shares for collection', function () {
        $owner = User::factory()->create();
        $targetUser1 = User::factory()->create();
        $targetUser2 = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->sharingService->shareCollection($collection, $targetUser1);
        $this->sharingService->shareCollection($collection, $targetUser2);

        $shares = $this->sharingService->getShares($collection);

        expect($shares)->toHaveCount(2);
    });
});
