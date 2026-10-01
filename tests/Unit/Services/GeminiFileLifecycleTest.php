<?php

use App\Exceptions\GeminiApiException;
use App\Jobs\Files\DeleteGeminiFile;
use App\Services\AI\FileManager\GeminiFileManager;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.providers.gemini.file_poll_delay_ms' => 0, 'ai.providers.gemini.file_poll_attempts' => 3]);
});

it('polls the exact resource path until active then deletes it', function () {
    Http::fake(['*' => Http::sequence()->push(['state' => 'PROCESSING'])->push(['state' => 'ACTIVE'])->push([], 204)]);
    $manager = new GeminiFileManager;
    expect($manager->waitUntilActive(['name' => 'files/abc', 'state' => 'PROCESSING'])['state'])->toBe('ACTIVE')
        ->and($manager->deleteFile('files/abc'))->toBeTrue();
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/files/abc?key=test');
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/files/abc?key=test');
});

it('rejects failed processing and bounds readiness polling', function (string $state, bool $retryable) {
    Http::fake(['*' => Http::response(['state' => $state])]);
    try {
        (new GeminiFileManager)->waitUntilActive(['name' => 'files/abc', 'state' => 'PROCESSING']);
        $this->fail('Expected readiness failure');
    } catch (GeminiApiException $exception) {
        expect($exception->isRetryable())->toBe($retryable);
    }
    Http::assertSentCount($retryable ? 3 : 1);
})->with([['FAILED', false], ['PROCESSING', true]]);

it('retains cleanup failures for a bounded queue retry', function () {
    Http::fake(['*' => Http::sequence()->push([], 503)->push([], 204)]);
    $job = new DeleteGeminiFile('files/abc');
    $manager = new GeminiFileManager;
    expect(fn () => $job->handle($manager))->toThrow(RuntimeException::class);
    $job->handle($manager);
    expect($job->tries)->toBe(3);
});
