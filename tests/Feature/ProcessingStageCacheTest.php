<?php

use App\Exceptions\AIResponseException;
use App\Models\File;
use App\Models\ProcessingUsageCounter;
use App\Models\User;
use App\Services\AI\Extractors\EntityExtractorFactory;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\EntityFactory;
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
    Http::assertSentCount(3);
    expect(ProcessingUsageBudget::usage($user->id, 'retry')['calls'])->toBe(2);
});

it('identifies exhausted usage budgets without making another provider request', function (string $limit, int $tokens): void {
    $user = User::factory()->create();
    config(['ai.limits.'.$limit => 1]);
    ProcessingUsageBudget::run($user->id, 'budget-report', 'classification', fn () => ProcessingUsageBudget::reserve(1));
    Http::fake();

    try {
        ProcessingUsageBudget::run($user->id, 'budget-report', 'extraction', fn () => ProcessingUsageBudget::reserve($tokens));
        $this->fail('The exhausted budget allowed another request.');
    } catch (AIResponseException $exception) {
        expect($exception->errorCode)->toBe(AIResponseException::CODE_USAGE_BUDGET_EXCEEDED)
            ->and($exception->retryable)->toBeFalse()
            ->and($exception->context['budget_scope'])->toBe(str_contains($limit, 'user_day') ? 'daily' : 'run')
            ->and($exception->context['retry_after'] !== null)->toBe(str_contains($limit, 'user_day'))
            ->and($exception->context)->toMatchArray(['stage' => 'extraction', 'run_calls' => 1, 'daily_calls' => 1, 'requested_tokens' => $tokens]);
    }
    expect(ProcessingUsageBudget::usage($user->id, 'budget-report')['calls'])->toBe(1);
    Http::assertNothingSent();
})->with([
    ['max_calls_per_run', 0], ['max_calls_per_user_day', 0],
    ['max_tokens_per_run', 1], ['max_tokens_per_user_day', 1],
]);

it('extracts a controlled receipt after correcting the daily processing limit', function (): void {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.limits.max_calls_per_user_day' => 1]);
    $file = File::factory()->create(['fileType' => 'application/pdf']);
    ProcessingUsageBudget::run($file->user_id, 'previous-file', 'extraction', fn () => ProcessingUsageBudget::reserve(1));
    $data = ['merchant_name' => 'Shop', 'total_amount' => 10, 'receipt_date' => '2024-01-01', 'description' => 'Purchase', 'category' => 'Other'];
    Http::fake([
        '*countTokens*' => Http::response(['totalTokens' => 100]),
        '*generateContent*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]]),
    ]);
    $extract = fn () => ProcessingUsageBudget::run($file->user_id, 'controlled-file', 'extraction', fn () => EntityExtractorFactory::create('receipt')->extract('https://gemini.test/file', $file));
    expect($extract)->toThrow(AIResponseException::class, 'Processing usage budget exceeded');
    Http::assertNothingSent();

    config(['ai.limits.max_calls_per_user_day' => 3]);
    $created = app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [$extract()]], $file, 'receipt');
    expect($created)->toHaveCount(1)
        ->and($created[0]['model']->total_amount)->toBe('10.00')
        ->and(ProcessingUsageBudget::usage($file->user_id, 'controlled-file')['calls'])->toBe(1);
    Http::assertSentCount(2);
});

it('releases unused reservations from the run stage and original UTC day', function (): void {
    $this->travelTo(now()->utc()->setTime(23, 59, 59));
    $user = User::factory()->create();
    $dayKey = 'daily:'.$user->id.':'.now()->utc()->format('Y-m-d');
    config(['ai.limits.max_tokens_per_run' => 1000, 'ai.limits.max_tokens_per_user_day' => 1000]);
    ProcessingUsageBudget::run($user->id, 'settled', 'extraction', function (): void {
        ProcessingUsageBudget::reserve(1000);
        $this->travel(2)->seconds();
        ProcessingUsageBudget::record(100, 50);
        ProcessingUsageBudget::reserve(800);
        ProcessingUsageBudget::record(200, 50);
    });
    $usage = ProcessingUsageBudget::usage($user->id, 'settled');
    expect($usage['reserved_tokens'])->toBe(400)
        ->and($usage['stages']['extraction'])->toMatchArray(['calls' => 2, 'reserved_tokens' => 400, 'input_tokens' => 300, 'output_tokens' => 100])
        ->and(ProcessingUsageCounter::query()->where('scope_key', $dayKey)->value('usage')['reserved_tokens'])->toBe(150)
        ->and(ProcessingUsageCounter::query()->where('scope_key', 'daily:'.$user->id.':'.now()->utc()->format('Y-m-d'))->value('usage')['reserved_tokens'])->toBe(250);
});

it('does not promise a daily reset for a request that cannot fit its daily allowance', function (): void {
    $user = User::factory()->create();
    config(['ai.limits.max_tokens_per_user_day' => 100]);
    try {
        ProcessingUsageBudget::run($user->id, 'oversized-request', 'extraction', fn () => ProcessingUsageBudget::reserve(101));
        $this->fail('An oversized request was allowed.');
    } catch (AIResponseException $exception) {
        expect($exception->context)->toMatchArray(['budget_scope' => 'request', 'limit' => 'daily_tokens', 'retry_after' => null]);
    }
    expect(ProcessingUsageBudget::usage($user->id, 'oversized-request')['calls'])->toBe(0);
});

it('processes a bulk receipt allowance beyond the former daily cap with the default limits', function (): void {
    $user = User::factory()->create();
    for ($file = 0; $file < 100; $file++) {
        foreach (['classification', 'extraction'] as $stage) {
            ProcessingUsageBudget::run($user->id, 'bulk-'.$file, $stage, function (): void {
                ProcessingUsageBudget::check();
                ProcessingUsageBudget::reserve(100000);
                ProcessingUsageBudget::record(2000, 1000);
            });
        }
    }
    $usage = ProcessingUsageCounter::query()->where('scope_key', 'daily:'.$user->id.':'.now()->utc()->format('Y-m-d'))->value('usage');
    expect($usage)->toMatchArray(['calls' => 200, 'reserved_tokens' => 600000]);
});

it('counts Gemini generation once and accounts for thinking tokens while retaining unknown usage', function (bool $hasUsage): void {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.limits.max_calls_per_run' => 1]);
    $response = ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"value":1}']]]]]];
    if ($hasUsage) {
        $response['usageMetadata'] = ['promptTokenCount' => 100, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 30, 'totalTokenCount' => 150];
    }
    Http::fake(['*countTokens*' => Http::response(['totalTokens' => 100]), '*generateContent*' => Http::response($response)]);
    $user = User::factory()->create();
    ProcessingUsageBudget::run($user->id, 'gemini-usage', 'extraction', fn () => app(GeminiProvider::class)->analyzeFileByUri('https://files.test/source', ['responseSchema' => ['type' => 'object']], 'Extract'));
    $usage = ProcessingUsageBudget::usage($user->id, 'gemini-usage');
    expect($usage['calls'])->toBe(1);
    if ($hasUsage) {
        expect($usage['reserved_tokens'])->toBe(150)
            ->and($usage['stages']['extraction']['output_tokens'])->toBe(50);
    } else {
        expect($usage['reserved_tokens'])->toBeGreaterThan(100);
    }
    Http::assertSentCount(2);
})->with([true, false]);

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

it('uses local PDF text for low confidence OCR and retains OCR text for invalid PDFs', function (bool $validPdf) {
    OCRServiceFactory::clearCache();
    config(['ai.ocr.provider' => 'textract', 'ai.ocr.options.min_confidence' => 0.8]);
    $path = sys_get_temp_dir().'/'.uniqid('ocr_fallback_', true).'.pdf';
    file_put_contents($path, $validPdf ? conversionPdfFixture() : 'invalid PDF');
    $this->mock(TextractProvider::class, function ($mock): void {
        $mock->shouldReceive('getProviderName')->andReturn('textract');
        $mock->shouldReceive('extractText')->once()->andReturn(OCRResult::success('Uncertain OCR', 'textract', confidence: 0.2));
    });

    try {
        $text = app(TextExtractionService::class)->extract($path, 'document', 'fallback-test');
        expect(trim($text))->toBe($validPdf ? 'Converted invoice' : 'Uncertain OCR');
    } finally {
        unlink($path);
        OCRServiceFactory::clearCache();
    }
})->with([true, false]);
