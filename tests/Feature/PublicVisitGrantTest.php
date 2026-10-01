<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\User;
use App\Services\PublicCollectionSharingService;
use App\Services\StorageService;

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
});

test('the final allowed visit can preview and download while new visits are exhausted', function () {
    $owner = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $file = File::factory()->create(['user_id' => $owner->id, 'fileExtension' => 'pdf']);
    $collection->files()->attach($file->id);
    $link = PublicCollectionLink::factory()->create(['collection_id' => $collection->id,
        'created_by_user_id' => $owner->id, 'max_views' => 1]);
    $this->get(route('shared.collections.show', $link->token))->assertOk();
    expect($link->fresh()->view_count)->toBe(1);
    $this->mock(StorageService::class, fn ($mock) => $mock->shouldReceive('getFileByUserAndGuid')->times(3)->andReturn('PDF bytes'));
    $url = route('shared.collections.file', [$link->token, $file->guid]);
    $this->get($url)->assertOk();
    $this->get($url.'?download=1')->assertOk();
    $this->get(route('shared.collections.download', $link->token))->assertOk();
    expect(app(PublicCollectionSharingService::class)->cleanupExpiredLinks())->toBe(0);
    $this->get($url)->assertOk();
    $this->get(route('shared.collections.show', $link->token))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Public/SharedCollectionExpired'));
    $this->flushSession();
    $this->get($url)->assertNotFound();
});

test('atomic reservations cannot exceed the view budget even with stale link models', function () {
    $link = PublicCollectionLink::factory()->create(['max_views' => 1]);
    $other = $link->fresh();
    expect($link->reserveView())->toBeTrue()->and($other->reserveView())->toBeFalse();
    expect($link->fresh()->view_count)->toBe(1);
});


test('changed passwords invalidate old public unlock and visit grants', function () {
    $link = PublicCollectionLink::factory()->create(['is_password_protected' => true,
        'password_hash' => \Illuminate\Support\Facades\Hash::make('first-password')]);
    $this->post(route('shared.collections.verify', $link->token), ['password' => 'first-password'])->assertRedirect();
    $this->get(route('shared.collections.show', $link->token))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Public/SharedCollection'));
    $version = $link->fresh()->access_version;
    $link->update(['password_hash' => \Illuminate\Support\Facades\Hash::make('changed-password')]);
    expect($link->fresh()->access_version)->toBe($version + 1);
    $this->get(route('shared.collections.show', $link->token))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Public/SharedCollectionPassword'));
    $this->post(route('shared.collections.verify', $link->token), ['password' => 'first-password'])->assertSessionHasErrors('password');
    $this->post(route('shared.collections.verify', $link->token), ['password' => 'changed-password'])->assertRedirect();
});

test('deleted collections are unavailable in every public action', function () {
    $link = PublicCollectionLink::factory()->create();
    $file = File::factory()->create(['user_id' => $link->collection->user_id]);
    $link->collection->files()->attach($file->id);
    $link->collection->delete();
    $this->get(route('shared.collections.show', $link->token))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Public/SharedCollectionExpired'));
    $this->post(route('shared.collections.verify', $link->token), ['password' => 'any-password'])->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Public/SharedCollectionExpired'));
    $this->get(route('shared.collections.file', [$link->token, $file->guid]))->assertNotFound();
    $this->get(route('shared.collections.download', $link->token))->assertNotFound();
});
