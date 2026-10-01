<?php

use App\Exceptions\AIResponseException;
use App\Models\File;
use App\Models\User;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\OCR\ExtractionCache;
use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRServiceFactory;
use App\Services\OCR\Providers\TextractProvider;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

it('reuses only matching tenant content stage prompt schema model and options', function () {
    Cache::flush();
    $calls = 0;
    $operation = function () use (&$calls): array {
        return ['value' => ++$calls];
    };
    $version = ['prompt' => 'Extract', 'schema' => ['type' => 'object'], 'model' => 'model-one', 'options' => ['temperature' => 0.2]];
    expect(ProcessingStageCache::remember(1, 'hash', 'extraction', $version, $operation))->toBe(['value' => 1])
        ->and(ProcessingStageCache::remember(1, 'hash', 'extraction', array_reverse($version, true), $operation))->toBe(['value' => 1]);
    ProcessingStageCache::remember(2, 'hash', 'extraction', $version, $operation);
    ProcessingStageCache::remember(1, 'changed', 'extraction', $version, $operation);
    ProcessingStageCache::remember(1, 'hash', 'classification', $version, $operation);
    foreach (['prompt', 'schema', 'model', 'options'] as $field) {
        ProcessingStageCache::remember(1, 'hash', 'extraction', [...$version, $field => 'changed'], $operation);
    }
    expect($calls)->toBe(8);
});

it('never caches failed or empty stages and expires successful stages', function () {
    Cache::flush();
    expect(fn () => ProcessingStageCache::remember(1, 'hash', 'empty', [], fn () => []))->toThrow(AIResponseException::class);
    $calls = 0;
    $operation = function () use (&$calls): array {
        $calls++;

        return ['success' => false];
    };
    ProcessingStageCache::remember(1, 'hash', 'failure', [], $operation);
    ProcessingStageCache::remember(1, 'hash', 'failure', [], $operation);
    expect($calls)->toBe(2);
    ProcessingStageCache::remember(1, 'hash', 'expiry', [], fn () => ['value' => 1]);
    $this->travel(25)->hours();
    expect(ProcessingStageCache::remember(1, 'hash', 'expiry', [], fn () => ['value' => 2]))->toBe(['value' => 2]);
});

it('enforces durable per-run and per-user budgets and records usage by stage', function () {
    $user = User::factory()->create();
    config(['ai.limits.max_calls_per_run' => 1, 'ai.limits.max_calls_per_user_day' => 2, 'ai.limits.max_tokens_per_run' => 10]);
    ProcessingUsageBudget::run($user->id, 'one', 'classification', function (): void {
        ProcessingUsageBudget::reserve(10);
        ProcessingUsageBudget::record(7, 3);
    });
    Cache::flush();
    expect(fn () => ProcessingUsageBudget::run($user->id, 'one', 'extraction', fn () => ProcessingUsageBudget::reserve(0)))->toThrow(AIResponseException::class);
    expect(ProcessingUsageBudget::usage($user->id, 'one')['stages']['classification']['input_tokens'])->toBe(7);
    ProcessingUsageBudget::run($user->id, 'two', 'extraction', fn () => ProcessingUsageBudget::reserve(1));
    expect(fn () => ProcessingUsageBudget::run($user->id, 'three', 'extraction', fn () => ProcessingUsageBudget::reserve(1)))->toThrow(AIResponseException::class);
    $other = User::factory()->create();
    ProcessingUsageBudget::run($other->id, 'one', 'classification', fn () => ProcessingUsageBudget::reserve(1));
    expect(ProcessingUsageBudget::usage($other->id, 'one')['calls'])->toBe(1);
});

it('counts and stops provider retries before they exceed the run budget', function () {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.providers.gemini.max_provider_retries' => 3, 'ai.limits.max_calls_per_run' => 2]);
    Http::fake(['*countTokens*' => Http::response(['totalTokens' => 100]), '*generateContent*' => Http::response(['error' => 'transient'], 503)]);
    $user = User::factory()->create();
    expect(fn () => ProcessingUsageBudget::run($user->id, 'retry', 'extraction', fn () => app(GeminiProvider::class)->analyzeFileByUri('https://files.test/source', ['responseSchema' => ['type' => 'object']], 'Extract')))->toThrow(AIResponseException::class);
    Http::assertSentCount(2);
    expect(ProcessingUsageBudget::usage($user->id, 'retry')['calls'])->toBe(2);
});

it('reuses OCR checkpoints by tenant and bytes, preserves partial coverage, and clears indexed stages', function () {
    OCRServiceFactory::clearCache();
    config(['ai.ocr.provider' => 'textract', 'ai.ocr.options.cache_results' => true]);
    $file = File::factory()->create(['fileExtension' => 'png']);
    $path = tempnam(sys_get_temp_dir(), 'ocr_cache_').'.png';
    file_put_contents($path, 'source-one');
    $calls = 0;
    $this->mock(TextractProvider::class, function ($mock) use (&$calls): void {
        $mock->shouldReceive('getProviderName')->andReturn('textract');
        $mock->shouldReceive('extractText')->times(3)->andReturnUsing(function () use (&$calls): OCRResult {
            return OCRResult::success('Text '.++$calls, 'textract', ['total_pages' => 2, 'processed_pages' => 1, 'needs_review' => true]);
        });
    });
    $service = app(TextExtractionService::class);
    try {
        expect($service->extract($path, 'document', $file->guid))->toBe('Text 1')
            ->and($service->extract($path, 'document', $file->guid))->toBe('Text 1')
            ->and($file->fresh()->status)->toBe('needs_review');
        file_put_contents($path, 'source-two');
        expect($service->extract($path, 'document', $file->guid))->toBe('Text 2');
        ExtractionCache::clear($file->guid);
        expect($service->extract($path, 'document', $file->guid))->toBe('Text 3');
    } finally {
        unlink($path);
        OCRServiceFactory::clearCache();
    }
});

it('bounds OpenAI fallback calls after a truncated first response', function () {
    config(['ai.limits.max_calls_per_run' => 1]);
    $chat = Mockery::mock();
    $chat->shouldReceive('create')->once()->andReturn((object) [
        'choices' => [(object) ['finishReason' => 'length', 'message' => (object) ['content' => '{"partial":']]],
        'usage' => (object) ['promptTokens' => 50, 'completionTokens' => 10],
    ]);
    $client = Mockery::mock();
    $client->shouldReceive('chat')->once()->andReturn($chat);
    OpenAI::swap($client);
    $user = User::factory()->create();
    $result = ProcessingUsageBudget::run($user->id, 'fallback', 'extraction', fn () => (new OpenAIProvider)->analyzeReceipt('Receipt text'));
    expect($result['success'])->toBeFalse()->and(ProcessingUsageBudget::usage($user->id, 'fallback')['calls'])->toBe(1)
        ->and(ProcessingUsageBudget::usage($user->id, 'fallback')['stages']['extraction']['output_tokens'])->toBe(10);
});
