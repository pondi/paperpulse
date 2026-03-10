<?php

declare(strict_types=1);

use App\Models\User;

it('includes content-security-policy header on web responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain('script-src')
        ->toContain('style-src')
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('upgrade-insecure-requests');
});

it('includes a nonce in the script-src directive', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/script-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/");
});

it('does not include unsafe-inline in script-src', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');

    // Extract the script-src directive
    preg_match('/script-src([^;]+)/', $csp, $matches);
    $scriptSrc = $matches[1] ?? '';

    expect($scriptSrc)->not->toContain("'unsafe-inline'");
    expect($scriptSrc)->not->toContain("'unsafe-eval'");
});

it('allows unsafe-inline only in style-src-attr for Vue dynamic bindings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');

    // style-src-attr should allow unsafe-inline for Vue :style bindings
    expect($csp)->toContain("style-src-attr 'unsafe-inline'");

    // style-src should NOT have unsafe-inline (uses nonces instead)
    preg_match('/(?<![a-z-])style-src([^;]+)/', $csp, $matches);
    $styleSrc = $matches[1] ?? '';

    expect($styleSrc)->not->toContain("'unsafe-inline'");
});

it('includes security headers alongside CSP', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy');
});

it('includes bunny fonts in style-src and font-src', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('fonts.bunny.net');
});
