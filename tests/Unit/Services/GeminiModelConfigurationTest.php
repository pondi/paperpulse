<?php

use App\Exceptions\GeminiApiException;
use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;

it('defaults to the latest economical model and respects environment overrides', function (?string $override, string $expectedModel) {
    $environment = Env::getRepository();
    $originalModel = $environment->get('GEMINI_MODEL');
    $environment->clear('GEMINI_MODEL');

    try {
        if ($override !== null) {
            $environment->set('GEMINI_MODEL', $override);
        }

        $configuration = require config_path('ai.php');

        expect($configuration['providers']['gemini']['model'])->toBe($expectedModel);
    } finally {
        $environment->clear('GEMINI_MODEL');
        if ($originalModel !== null) {
            $environment->set('GEMINI_MODEL', $originalModel);
        }
    }
})->with([
    'default' => [null, 'gemini-3.5-flash-lite'],
    'override' => ['gemini-3.8-flash', 'gemini-3.8-flash'],
]);

it('uses the selected model for structured text and file extraction', function (string $input, ?string $configuredModel, string $expectedModel) {
    config(['ai.providers.gemini.api_key' => 'test-key']);
    $geminiConfiguration = config('ai.providers.gemini');
    unset($geminiConfiguration['model']);
    config(['ai.providers.gemini' => $geminiConfiguration]);
    if ($configuredModel !== null) {
        config(['ai.providers.gemini.model' => $configuredModel]);
    }

    $schema = [
        'type' => 'object',
        'properties' => ['total' => ['type' => 'number']],
        'required' => ['total'],
    ];

    Http::preventStrayRequests();
    Http::fake([
        "generativelanguage.googleapis.com/v1beta/models/{$expectedModel}:countTokens*" => Http::response(['totalTokens' => 100]),
        "generativelanguage.googleapis.com/v1beta/models/{$expectedModel}:generateContent*" => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => '{"total":0}']]],
            ]],
        ]),
    ]);

    $provider = new GeminiProvider;
    if ($input === 'text') {
        $result = $provider->generateText('Extract the total.', $schema);
    } else {
        $result = $provider->analyzeFileByUri(
            'https://generativelanguage.googleapis.com/v1beta/files/test-file',
            ['name' => 'receipt', 'responseSchema' => $schema],
            'Extract the total.',
        );
        expect($result['model'])->toBe($expectedModel);
        $result = $result['data'];
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/{$expectedModel}:countTokens"));
    }

    expect($result)->toBe(['total' => 0]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/{$expectedModel}:generateContent")
        && $request['generationConfig']['responseJsonSchema'] === $schema
        && $request['generationConfig']['responseMimeType'] === 'application/json');
    Http::assertSentCount($input === 'text' ? 1 : 2);
})->with(['text', 'file'])->with([
    'default fallback' => [null, 'gemini-3.5-flash-lite'],
    'selected model' => ['gemini-3.5-flash-lite', 'gemini-3.5-flash-lite'],
    'override' => ['gemini-3.8-flash', 'gemini-3.8-flash'],
]);

it('rejects missing credentials before requesting the selected model', function () {
    config([
        'ai.providers.gemini.api_key' => null,
        'ai.providers.gemini.model' => 'gemini-3.5-flash-lite',
    ]);
    Http::fake();

    expect(fn () => (new GeminiProvider)->generateText('Extract the total.'))
        ->toThrow(GeminiApiException::class, 'Missing Gemini API key.');

    Http::assertNothingSent();
});
