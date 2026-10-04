<?php

declare(strict_types=1);

use App\Models\User;
use Symfony\Component\Process\Process;

it('includes content-security-policy header on web responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('https://localhost/dashboard');

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

it('upgrades requests on HTTPS and production while allowing local HTTP', function (string $environment, string $scheme, bool $upgrades): void {
    app()->detectEnvironment(fn () => $environment);
    $response = $this->get($scheme.'://localhost/login');
    $response->assertSuccessful();
    expect(str_contains($response->headers->get('Content-Security-Policy'), 'upgrade-insecure-requests'))->toBe($upgrades);
})->with([
    ['local', 'http', false],
    ['testing', 'http', false],
    ['local', 'https', true],
    ['testing', 'https', true],
    ['production', 'http', true],
    ['production', 'https', true],
]);

it('includes a nonce in the script-src directive', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/script-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/");
});

it('exposes the style nonce to the Inertia runtime', function (): void {
    $response = $this->get('/login');
    $response->assertSuccessful();

    preg_match("/(?<![a-z-])style-src[^;]*'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $matches);

    $response->assertSee('<meta name="csp-nonce" content="'.$matches[1].'">', false);
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

it('permits the configured production websocket and wasm without JavaScript eval', function (string $scheme, string $websocket) {
    app()->detectEnvironment(fn () => 'production');
    config([
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.options.host' => 'notifications.example.com',
        'broadcasting.connections.reverb.options.port' => 8443,
        'broadcasting.connections.reverb.options.scheme' => $scheme,
    ]);
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('scanner'));
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain($websocket.'://notifications.example.com:8443')
        ->toContain("'wasm-unsafe-eval'")
        ->not->toContain("'unsafe-eval'", 'localhost', 'wss://*', 'ws://*');
})->with([['https', 'wss'], ['http', 'ws']]);

it('does not permit unconfigured production websocket origins', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['broadcasting.connections.reverb.key' => null]);
    $response = $this->actingAs(User::factory()->create())->get(route('scanner'));

    expect($response->headers->get('Content-Security-Policy'))->not->toContain('wss://', 'ws://');
});

it('starts the shipped scanner runtime with dynamic JavaScript disabled', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const context = vm.createContext({
    module: { exports: {} }, require, process, __dirname: process.cwd() + '/public/vendor',
    setTimeout, clearTimeout, console, Buffer,
}, { codeGeneration: { strings: false, wasm: true } });
vm.runInContext(fs.readFileSync('public/vendor/opencv.js', 'utf8'), context);
const cv = context.module.exports;
let initialized = false;
process.on('exit', () => assert.equal(initialized, true));
cv.onRuntimeInitialized = () => {
    initialized = true;
    const source = cv.Mat.ones(8, 8, cv.CV_8UC4);
    const gray = new cv.Mat();
    try {
        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        assert.equal(gray.rows, 8);
        assert.equal(gray.cols, 8);
        const points = cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, 7, 0, 7, 7, 0, 7]);
        const transform = cv.getPerspectiveTransform(points, points);
        const output = new cv.Mat();
        try {
            cv.warpPerspective(source, output, transform, new cv.Size(8, 8));
            assert.equal(output.rows, 8);
        } finally {
            points.delete();
            transform.delete();
            output.delete();
        }
    } finally {
        source.delete();
        gray.delete();
    }
};
JS;
    $process = new Process(['node', '-e', $script], base_path());
    $process->setTimeout(30)->run();

    expect($process->isSuccessful())->toBeTrue(substr($process->getErrorOutput(), -1500));
});
