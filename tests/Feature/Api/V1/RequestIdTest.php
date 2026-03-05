<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('request id middleware', function () {
    it('generates X-Request-ID when not provided', function () {
        $response = $this->getJson('/api/health');

        $response->assertSuccessful();
        $requestId = $response->headers->get('X-Request-ID');
        expect($requestId)->not->toBeNull();
        expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('echoes back client-provided X-Request-ID', function () {
        $clientRequestId = 'my-custom-request-id-123';

        $response = $this->getJson('/api/health', [
            'X-Request-ID' => $clientRequestId,
        ]);

        $response->assertSuccessful();
        expect($response->headers->get('X-Request-ID'))->toBe($clientRequestId);
    });

    it('includes X-Request-ID on authenticated API routes', function () {
        $response = $this->getJson(route('api.tags.index'));

        $response->assertSuccessful();
        expect($response->headers->get('X-Request-ID'))->not->toBeNull();
    });
});
