<?php

use App\Models\Collection;
use App\Models\PublicCollectionLink;
use App\Models\User;

it('includes data uris in the connect-src policy', function () {
    $response = $this->get('/');

    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('connect-src');
    expect($csp)->toContain('data:');
});

it('allows camera access from self', function () {
    $response = $this->get('/');

    $response->assertOk();

    $permissionsPolicy = $response->headers->get('Permissions-Policy');

    expect($permissionsPolicy)->toContain('camera=(self)');
});

it('prevents caching private pages and file responses', function (): void {
    $this->withoutVite();
    $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('prevents public share tokens and data from leaking through referrers and browser caches', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $link = PublicCollectionLink::create(['collection_id' => $collection->id, 'created_by_user_id' => $owner->id]);
    $response = $this->get('/shared/collections/'.$link->token)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});
