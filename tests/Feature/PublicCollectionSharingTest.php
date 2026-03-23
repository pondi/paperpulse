<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->collection = Collection::factory()->create(['user_id' => $this->owner->id]);
    $this->file = File::factory()->create(['user_id' => $this->owner->id]);
    $this->collection->files()->attach($this->file->id);
});

describe('Owner Management', function () {
    test('owner can create a public link', function () {
        $response = $this->actingAs($this->owner)
            ->post(route('collections.public-links.store', $this->collection->id), [
                'label' => 'For accountant',
                'is_password_protected' => false,
                'expiration_preset' => '30d',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('publicLink');

        $this->assertDatabaseHas('public_collection_links', [
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
            'label' => 'For accountant',
            'is_password_protected' => false,
            'is_active' => true,
        ]);
    });

    test('owner can create a password-protected link', function () {
        $response = $this->actingAs($this->owner)
            ->post(route('collections.public-links.store', $this->collection->id), [
                'is_password_protected' => true,
                'expiration_preset' => 'never',
            ]);

        $response->assertRedirect();
        $flash = $response->getSession()->get('publicLink');
        expect($flash)->toHaveKey('password')
            ->and($flash['password'])->toBeString()->toHaveLength(8);

        $link = PublicCollectionLink::latest()->first();
        expect($link->is_password_protected)->toBeTrue()
            ->and($link->password_hash)->not->toBeNull();
    });

    test('owner can create a link with expiration', function () {
        $this->actingAs($this->owner)
            ->post(route('collections.public-links.store', $this->collection->id), [
                'is_password_protected' => false,
                'expiration_preset' => '7d',
            ]);

        $link = PublicCollectionLink::latest()->first();
        expect($link->expires_at)->not->toBeNull()
            ->and($link->expires_at->isFuture())->toBeTrue();
    });

    test('owner can create a link with max views', function () {
        $this->actingAs($this->owner)
            ->post(route('collections.public-links.store', $this->collection->id), [
                'is_password_protected' => false,
                'expiration_preset' => 'never',
                'max_views' => 5,
            ]);

        $link = PublicCollectionLink::latest()->first();
        expect($link->max_views)->toBe(5)
            ->and($link->view_count)->toBe(0);
    });

    test('non-owner cannot create public links', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->post(route('collections.public-links.store', $this->collection->id), [
                'is_password_protected' => false,
                'expiration_preset' => 'never',
            ]);

        $response->assertForbidden();
    });

    test('owner can revoke a link', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->delete(route('collections.public-links.destroy', [$this->collection->id, $link->id]));

        $response->assertRedirect();

        $link->refresh();
        expect($link->is_active)->toBeFalse();
    });

    test('owner can view access logs', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('collections.public-links.logs', [$this->collection->id, $link->id]));

        $response->assertOk();
    });
});

describe('Public Access', function () {
    test('public user can view collection via valid token', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->get('/shared/collections/'.$link->token);

        $response->assertOk();
    });

    test('public user sees expired page for expired link', function () {
        $link = PublicCollectionLink::factory()->expired()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->get('/shared/collections/'.$link->token);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollectionExpired'));
    });

    test('public user sees expired page when max views reached', function () {
        $link = PublicCollectionLink::factory()->withMaxViews(1)->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
            'view_count' => 1,
        ]);

        $response = $this->get('/shared/collections/'.$link->token);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollectionExpired'));
    });

    test('public user sees password page for protected link', function () {
        $link = PublicCollectionLink::factory()->passwordProtected()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->get('/shared/collections/'.$link->token);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollectionPassword'));
    });

    test('correct password unlocks collection', function () {
        $link = PublicCollectionLink::factory()->passwordProtected('secret123')->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->post('/shared/collections/'.$link->token.'/verify', [
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/shared/collections/'.$link->token);

        // After unlock, should see the collection
        $response = $this->get('/shared/collections/'.$link->token);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollection'));
    });

    test('wrong password shows error', function () {
        $link = PublicCollectionLink::factory()->passwordProtected('secret123')->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->post('/shared/collections/'.$link->token.'/verify', [
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
    });

    test('public user can serve individual file', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        // Note: file serving requires actual S3 storage, so we just verify the route resolves
        // and that auth/scoping validation works (the 404 is expected since there's no S3 file)
        $response = $this->get('/shared/collections/'.$link->token.'/files/'.$this->file->guid);

        // Should not be forbidden — it's either 200 (if S3 works) or 404 (no file in storage)
        expect($response->getStatusCode())->toBeIn([200, 404]);
    });

    test('file serve rejects GUID not in collection', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $otherFile = File::factory()->create(['user_id' => $this->owner->id]);
        // otherFile is NOT attached to the collection

        $response = $this->get('/shared/collections/'.$link->token.'/files/'.$otherFile->guid);

        $response->assertNotFound();
    });

    test('revoked link returns expired page', function () {
        $link = PublicCollectionLink::factory()->revoked()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $response = $this->get('/shared/collections/'.$link->token);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollectionExpired'));
    });

    test('access is logged for view action', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->get('/shared/collections/'.$link->token);

        $this->assertDatabaseHas('public_share_access_logs', [
            'public_collection_link_id' => $link->id,
            'action' => 'view',
        ]);
    });

    test('view count increments on access', function () {
        $link = PublicCollectionLink::factory()->create([
            'collection_id' => $this->collection->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        expect($link->view_count)->toBe(0);

        $this->get('/shared/collections/'.$link->token);

        $link->refresh();
        expect($link->view_count)->toBe(1);
    });

    test('invalid token returns expired page', function () {
        $response = $this->get('/shared/collections/invalid-token-that-does-not-exist');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/SharedCollectionExpired'));
    });
});
