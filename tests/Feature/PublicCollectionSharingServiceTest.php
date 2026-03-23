<?php

use App\Models\Collection;
use App\Models\PublicCollectionLink;
use App\Models\User;
use App\Services\PublicCollectionSharingService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->collection = Collection::factory()->create(['user_id' => $this->user->id]);
    $this->service = app(PublicCollectionSharingService::class);
});

describe('createLink', function () {
    test('generates valid token and URL', function () {
        $this->actingAs($this->user);

        $result = $this->service->createLink($this->collection);

        expect($result)->toHaveKeys(['link', 'password', 'url'])
            ->and($result['link']->token)->toBeString()->toHaveLength(64)
            ->and($result['url'])->toContain('/shared/collections/')
            ->and($result['password'])->toBeNull();
    });

    test('with password generates hash and returns plaintext', function () {
        $this->actingAs($this->user);

        $result = $this->service->createLink($this->collection, [
            'is_password_protected' => true,
        ]);

        expect($result['password'])->toBeString()->toHaveLength(8)
            ->and($result['link']->is_password_protected)->toBeTrue()
            ->and($result['link']->password_hash)->not->toBeNull();
    });

    test('with label stores label', function () {
        $this->actingAs($this->user);

        $result = $this->service->createLink($this->collection, [
            'label' => 'For accountant 2026',
        ]);

        expect($result['link']->label)->toBe('For accountant 2026');
    });
});

describe('verifyPassword', function () {
    test('accepts correct password', function () {
        $this->actingAs($this->user);

        $result = $this->service->createLink($this->collection, [
            'is_password_protected' => true,
        ]);

        $isValid = $this->service->verifyPassword($result['link'], $result['password']);

        expect($isValid)->toBeTrue();
    });

    test('rejects wrong password', function () {
        $this->actingAs($this->user);

        $result = $this->service->createLink($this->collection, [
            'is_password_protected' => true,
        ]);

        $isValid = $this->service->verifyPassword($result['link'], 'wrongpassword');

        expect($isValid)->toBeFalse();
    });
});

describe('findActiveLink', function () {
    test('returns active link', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $found = $this->service->findActiveLink($link->token);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($link->id);
    });

    test('returns null for expired token', function () {
        $link = PublicCollectionLink::factory()->expired()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $found = $this->service->findActiveLink($link->token);

        expect($found)->toBeNull();
    });

    test('returns null for revoked token', function () {
        $link = PublicCollectionLink::factory()->revoked()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $found = $this->service->findActiveLink($link->token);

        expect($found)->toBeNull();
    });
});

describe('cleanupExpiredLinks', function () {
    test('deactivates expired links', function () {
        $expired = PublicCollectionLink::factory()->expired()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $active = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $count = $this->service->cleanupExpiredLinks();

        expect($count)->toBe(1);
        expect($expired->fresh()->is_active)->toBeFalse();
        expect($active->fresh()->is_active)->toBeTrue();
    });

    test('deactivates links that exceeded max views', function () {
        $exceeded = PublicCollectionLink::factory()->withMaxViews(1)->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
            'view_count' => 1,
            'is_active' => true,
        ]);

        $count = $this->service->cleanupExpiredLinks();

        expect($count)->toBe(1);
        expect($exceeded->fresh()->is_active)->toBeFalse();
    });
});

describe('revokeLink', function () {
    test('sets is_active to false', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->service->revokeLink($link);

        expect($link->fresh()->is_active)->toBeFalse();
    });
});
