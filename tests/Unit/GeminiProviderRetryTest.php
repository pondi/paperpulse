<?php

declare(strict_types=1);

use App\Exceptions\GeminiApiException;
use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'ai.providers.gemini.api_key' => 'test-key',
        'ai.providers.gemini.model' => 'gemini-2.0-flash',
        'ai.providers.gemini.timeout' => 10,
    ]);
});

it('retries transient 503 errors at the provider level and succeeds', function () {
    $successResponse = [
        'candidates' => [[
            'content' => [
                'parts' => [['text' => '{"entities": [{"type": "receipt"}]}']],
            ],
        ]],
    ];

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 503, 'message' => 'UNAVAILABLE']], 503)
            ->push(['error' => ['code' => 503, 'message' => 'UNAVAILABLE']], 503)
            ->push($successResponse, 200),
    ]);

    $provider = new GeminiProvider;
    $result = $provider->generateText('Test prompt', null, 0.1);

    expect($result)->toHaveKey('entities');
    Http::assertSentCount(3);
});

it('throws after exhausting all provider retries on persistent 503', function () {
    Http::fake(fn () => Http::response(
        ['error' => ['code' => 503, 'message' => 'UNAVAILABLE']],
        503
    ));

    $provider = new GeminiProvider;
    $provider->generateText('Test prompt');
})->throws(GeminiApiException::class);

it('marks persistent 503 exception as retryable for queue-level retry', function () {
    Http::fake(fn () => Http::response(
        ['error' => ['code' => 503, 'message' => 'UNAVAILABLE']],
        503
    ));

    $provider = new GeminiProvider;

    try {
        $provider->generateText('Test prompt');
    } catch (GeminiApiException $e) {
        expect($e->isRetryable())->toBeTrue();
        expect($e->getContext())->toHaveKey('provider_attempts', 3);

        return;
    }

    $this->fail('Expected GeminiApiException was not thrown');
});

it('does not retry non-retryable status codes like 400', function () {
    Http::fake(fn () => Http::response(
        ['error' => ['code' => 400, 'message' => 'Bad request']],
        400
    ));

    $provider = new GeminiProvider;

    try {
        $provider->generateText('Test prompt');
    } catch (GeminiApiException $e) {
        expect($e->isRetryable())->toBeFalse();
        Http::assertSentCount(1);

        return;
    }

    $this->fail('Expected GeminiApiException was not thrown');
});

it('retries 429 rate limit errors at the provider level', function () {
    $successResponse = [
        'candidates' => [[
            'content' => [
                'parts' => [['text' => '{"result": "ok"}']],
            ],
        ]],
    ];

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 429, 'message' => 'Rate limited']], 429)
            ->push($successResponse, 200),
    ]);

    $provider = new GeminiProvider;
    $result = $provider->generateText('Test prompt');

    expect($result)->toHaveKey('result', 'ok');
    Http::assertSentCount(2);
});
