<?php

use App\Exceptions\GeminiApiException;
use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;

it('prevents active URI extraction beyond its configured input token budget', function () {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.providers.gemini.max_input_tokens' => 100]);
    Http::fake(['*countTokens*' => Http::response(['totalTokens' => 101])]);
    expect(fn () => (new GeminiProvider)->analyzeFileByUri('https://gemini.test/file', [], 'Extract', [], 'text/plain'))->toThrow(GeminiApiException::class);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), ':generateContent'));
});

it('sets the output token cap on the active URI pipeline', function () {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.providers.gemini.max_output_tokens' => 512]);
    Http::fake(['*countTokens*' => Http::response(['totalTokens' => 100]), '*generateContent*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"title":"Document"}']]]]],
    ])]);
    (new GeminiProvider)->analyzeFileByUri('https://gemini.test/file', [], 'Extract', [], 'text/plain');
    Http::assertSent(fn ($request) => str_contains($request->url(), ':generateContent') && $request['generationConfig']['maxOutputTokens'] === 512);
});
