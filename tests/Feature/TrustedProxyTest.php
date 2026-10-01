<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_test/proxy', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'url' => $request->url(),
    ]));
});

it('ignores forwarded headers from an untrusted client', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->getJson('http://localhost/_test/proxy', [
            'X-Forwarded-For' => '203.0.113.99',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Proto' => 'https',
        ])
        ->assertOk()
        ->assertJsonPath('ip', '198.51.100.10')
        ->assertJsonPath('url', 'http://localhost/_test/proxy');
});

it('accepts configured proxy IP and scheme headers without accepting forwarded hosts', function (): void {
    config()->set('network.trusted_proxies', ['192.0.2.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->getJson('http://localhost/_test/proxy', [
            'X-Forwarded-For' => '203.0.113.99',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Proto' => 'https',
        ])
        ->assertOk()
        ->assertJsonPath('ip', '203.0.113.99')
        ->assertJsonPath('url', 'https://localhost/_test/proxy');
});

it('rejects direct requests for an unintended production host', function (): void {
    $this->app->instance('env', 'production');
    config()->set('network.trusted_hosts', ['^paperpulse\\.test$']);

    try {
        $this->getJson('http://untrusted.example/_test/proxy')->assertBadRequest();
    } finally {
        Request::setTrustedHosts([]);
    }
});
