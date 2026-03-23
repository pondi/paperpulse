<?php

use App\Models\Collection;
use App\Models\PublicCollectionLink;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->collection = Collection::factory()->create(['user_id' => $this->user->id]);
});

describe('Token Generation', function () {
    test('token is auto-generated on create', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->token)->toBeString()->toHaveLength(64);
    });

    test('token is unique', function () {
        $link1 = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);
        $link2 = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link1->token)->not->toBe($link2->token);
    });
});

describe('Accessibility', function () {
    test('isAccessible returns true for active unexpired link', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->isAccessible())->toBeTrue();
    });

    test('isAccessible returns false for expired link', function () {
        $link = PublicCollectionLink::factory()->expired()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->isAccessible())->toBeFalse();
    });

    test('isAccessible returns false for revoked link', function () {
        $link = PublicCollectionLink::factory()->revoked()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->isAccessible())->toBeFalse();
    });

    test('isAccessible returns false when max views reached', function () {
        $link = PublicCollectionLink::factory()->withMaxViews(5)->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
            'view_count' => 5,
        ]);

        expect($link->isAccessible())->toBeFalse();
    });

    test('isAccessible returns true when views below max', function () {
        $link = PublicCollectionLink::factory()->withMaxViews(5)->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
            'view_count' => 3,
        ]);

        expect($link->isAccessible())->toBeTrue();
    });
});

describe('Expiration', function () {
    test('hasExpired returns true for past date', function () {
        $link = PublicCollectionLink::factory()->expired()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->hasExpired())->toBeTrue();
    });

    test('hasExpired returns false for null expires_at', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
            'expires_at' => null,
        ]);

        expect($link->hasExpired())->toBeFalse();
    });

    test('hasExpired returns false for future date', function () {
        $link = PublicCollectionLink::factory()->expiresIn(7)->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->hasExpired())->toBeFalse();
    });
});

describe('Serialization', function () {
    test('password_hash is hidden from serialization', function () {
        $link = PublicCollectionLink::factory()->passwordProtected()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $array = $link->toArray();

        expect($array)->not->toHaveKey('password_hash');
    });
});

describe('Revocation', function () {
    test('revoke sets is_active to false', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $link->revoke();

        expect($link->fresh()->is_active)->toBeFalse();
    });
});

describe('View Count', function () {
    test('incrementViewCount increases view_count by 1', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->view_count)->toBe(0);

        $link->incrementViewCount();

        expect($link->fresh()->view_count)->toBe(1);
    });

    test('incrementViewCount updates last_accessed_at', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        expect($link->last_accessed_at)->toBeNull();

        $link->incrementViewCount();

        expect($link->fresh()->last_accessed_at)->not->toBeNull();
    });
});
