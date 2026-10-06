<?php

use App\Exceptions\AIResponseException;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\ReturnPolicy;
use App\Services\AI\Extractors\EntityExtractorFactory;
use App\Services\AI\Extractors\ReturnPolicy\ReturnPolicyValidator;
use App\Services\EntityFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('requests calendar deadlines and persists relative terms without inventing dates', function (string $type, array $policy): void {
    config(['ai.providers.gemini.api_key' => 'test']);
    $data = $type === 'receipt' ? [
        'merchant_name' => 'Shop', 'total_amount' => 10, 'receipt_date' => '2025-02-16',
        'description' => 'Purchase', 'category' => 'Other', 'return_policies' => [$policy],
    ] : $policy;
    Http::fake(['*' => Http::response(['totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]])]);
    $file = File::factory()->create(['fileType' => 'application/pdf']);
    $extracted = EntityExtractorFactory::create($type)->extract('https://gemini.test/file', $file);
    app(EntityFactory::class)->createEntitiesFromParsedData([
        'entities' => array_merge([$extracted], $extracted['supplemental_entities'] ?? []),
    ], $file, $type);

    $stored = ReturnPolicy::firstOrFail();
    expect($stored->conditions)->toBe($policy['conditions'])
        ->and($stored->return_deadline?->format('Y-m-d'))->toBe($policy['return_deadline'] ?? null)
        ->and($stored->exchange_deadline?->format('Y-m-d'))->toBe($policy['exchange_deadline'] ?? null);
    Http::assertSent(function (Request $request) use ($type): bool {
        if (! str_contains($request->url(), ':generateContent')) {
            return false;
        }
        $schema = $request['generationConfig']['responseJsonSchema'];
        $properties = $type === 'receipt' ? $schema['properties']['return_policies']['items']['properties'] : $schema['properties'];

        return $properties['return_deadline']['format'] === 'date'
            && $properties['exchange_deadline']['format'] === 'date'
            && str_contains($request['contents'][0]['parts'][0]['text'], 'relative durations');
    });
})->with([
    'receipt relative terms' => ['receipt', ['conditions' => 'Return within 30 days']],
    'standalone relative terms' => ['return_policy', ['conditions' => 'Return within 30 days']],
    'receipt explicit dates' => ['receipt', ['conditions' => 'Return or exchange by the printed date', 'return_deadline' => '2025-03-18', 'exchange_deadline' => '2025-03-25']],
    'standalone explicit dates' => ['return_policy', ['conditions' => 'Return or exchange by the printed date', 'return_deadline' => '2025-03-18', 'exchange_deadline' => '2025-03-25']],
]);

it('extracts and persists a standalone return policy as a primary entity', function () {
    config(['ai.providers.gemini.api_key' => 'test']);
    Http::fake(['*' => Http::response(['totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode([
        'conditions' => 'Unopened items accepted', 'requires_receipt' => false, 'is_final_sale' => false, 'restocking_fee' => 0,
    ])]]]]]])]);
    $file = File::factory()->create(['fileType' => 'application/pdf']);
    $extracted = EntityExtractorFactory::create('return_policy')->extract('https://gemini.test/file', $file);
    $created = app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [$extracted]], $file, 'return_policy');
    expect($created)->toHaveCount(1)
        ->and(ReturnPolicy::first()->requires_receipt)->toBeFalse()
        ->and(ReturnPolicy::first()->restocking_fee)->toBe('0.00')
        ->and(ExtractableEntity::first()->is_primary)->toBeTrue();
});

it('keeps supplemental policies and warranties linked to their primary receipt', function () {
    config(['ai.providers.gemini.api_key' => 'test']);
    $data = ['merchant_name' => 'Shop', 'total_amount' => 10, 'receipt_date' => '2024-01-01', 'description' => 'Purchase', 'category' => 'Other',
        'return_policies' => [['conditions' => 'Return within 30 days', 'requires_receipt' => false]],
        'warranties' => [['provider_name' => 'Shop', 'product_name' => 'Device', 'warranty_end_date' => '2026-01-01']]];
    Http::fake(['*' => Http::response(['totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]])]);
    $file = File::factory()->create(['fileType' => 'application/pdf']);
    $extracted = EntityExtractorFactory::create('receipt')->extract('https://gemini.test/file', $file);
    $created = app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => array_merge([$extracted], $extracted['supplemental_entities'])], $file, 'receipt');
    expect($created)->toHaveCount(3)
        ->and($created[1]['model']->receipt_id)->toBe($created[0]['model']->id)
        ->and($created[2]['model']->receipt_id)->toBe($created[0]['model']->id)
        ->and(ExtractableEntity::where('is_primary', true)->count())->toBe(1)
        ->and(ExtractableEntity::where('is_primary', false)->count())->toBe(2);
});

it('rejects incomplete policies and invalid dates or fees', function (array $data) {
    expect((new ReturnPolicyValidator)->validate($data)['valid'])->toBeFalse();
})->with([
    [[]], [['conditions' => 'Return', 'return_deadline' => '2024-02-30']],
    [['conditions' => 'Return', 'restocking_fee_percentage' => 101]],
]);

it('reports the failing supplemental field and validation errors', function (): void {
    config(['ai.providers.gemini.api_key' => 'test']);
    $data = ['merchant_name' => 'Shop', 'total_amount' => 10, 'receipt_date' => '2024-01-01', 'description' => 'Purchase', 'category' => 'Other',
        'return_policies' => [['conditions' => 'Return', 'return_deadline' => '2024-02-30']],
    ];
    Http::fake(['*' => Http::response(['totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]])]);
    $file = File::factory()->create(['fileType' => 'application/pdf']);

    try {
        EntityExtractorFactory::create('receipt')->extract('https://gemini.test/file', $file);
        $this->fail('An invalid supplemental policy was accepted.');
    } catch (AIResponseException $exception) {
        expect($exception->context['field'])->toBe('return_policies')
            ->and($exception->context['errors'])->not->toBeEmpty()
            ->and($exception->context['dates'])->toBe(['return_deadline' => '2024-02-30'])
            ->and($exception->getMessage())->toContain('return deadline')
            ->and($exception->retryable)->toBeFalse();
    }
});
