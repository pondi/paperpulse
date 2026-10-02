<?php

use App\Models\Collection;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\HistoricalCurrencyService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    Http::preventStrayRequests();
});

function historicalRateCsv(string $currency, string $date, string $rate, int $multiplier = 0): string
{
    return "BASE_CUR;QUOTE_CUR;UNIT_MULT;TIME_PERIOD;OBS_VALUE\n{$currency};NOK;{$multiplier};{$date};{$rate}\n";
}

it('converts through NOK with quoted units and reuses persisted historical observations', function (): void {
    Http::fake([
        '*B.JPY.NOK.SP*' => Http::response(historicalRateCsv('JPY', '2026-09-25', '6', 2)),
        '*B.EUR.NOK.SP*' => Http::response(historicalRateCsv('EUR', '2026-09-25', '12')),
    ]);
    $service = new HistoricalCurrencyService;
    expect($service->convert(100, 'JPY', '2026-09-27', 'EUR'))->toBe(0.5)
        ->and((new HistoricalCurrencyService)->convert(10, 'EUR', '2026-09-27', 'NOK'))->toBe(120.0);
    Http::assertSentCount(2);
    $this->assertDatabaseHas('exchange_rate_observations', ['provider' => 'norges-bank', 'currency' => 'JPY', 'units' => 100]);
    Http::assertSent(fn ($request): bool => $request['endPeriod'] === '2026-09-27' && $request['lastNObservations'] === 1);
});

it('preserves same-currency totals and makes missing historical conversion unavailable', function (?string $date, string $currency, string $response, int $status): void {
    Http::fake(['*' => Http::response($response, $status)]);
    $service = new HistoricalCurrencyService;
    expect($service->convert(0, 'NOK', null, 'NOK'))->toBe(0.0)
        ->and($service->convert(50, $currency, $date, 'NOK'))->toBeNull();
})->with([
    'missing date' => [null, 'EUR', '', 200],
    'unsupported currency' => ['2026-09-27', 'XYZ', '', 404],
    'provider down' => ['2026-09-27', 'EUR', '', 503],
    'future rate' => ['2026-09-27', 'EUR', historicalRateCsv('EUR', '2026-09-28', '12'), 200],
    'invalid units' => ['2026-09-27', 'EUR', historicalRateCsv('EUR', '2026-09-25', '12', -1), 200],
]);

it('uses dated conversion in owner summaries without changing original amounts', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Http::fake(['*' => Http::response(historicalRateCsv('EUR', '2026-09-25', '12'))]);
    Receipt::factory()->create(['user_id' => $user->id, 'total_amount' => 100, 'tax_amount' => 0, 'currency' => 'NOK', 'receipt_date' => '2026-09-27']);
    $foreignCurrency = Receipt::factory()->create(['user_id' => $user->id, 'total_amount' => 10, 'tax_amount' => 1, 'currency' => 'EUR', 'receipt_date' => '2026-09-27']);
    Receipt::factory()->create(['user_id' => User::factory()->create()->id, 'total_amount' => 99999, 'currency' => 'NOK']);

    $this->get(route('analytics.index', ['tab' => 'receipts']))->assertInertia(fn (Assert $page) => $page
        ->where('tab_data.stats.total', 220)->where('tab_data.stats.tax', 12));
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('totalAmount', 220));
    expect($foreignCurrency->fresh()->total_amount)->toBe('10.00')->and($foreignCurrency->fresh()->currency)->toBe('EUR');
    Http::assertSentCount(1);
});

it('never reports a partial converted total as complete', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Http::fake(['*' => Http::response('', 503)]);
    Receipt::factory()->create(['user_id' => $user->id, 'total_amount' => 100, 'currency' => 'NOK']);
    Receipt::factory()->create(['user_id' => $user->id, 'total_amount' => 10, 'currency' => 'EUR']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('totalAmount', null));
});

it('counts an overlapping receipt and invoice only once in combined spending', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $file = File::factory()->create(['user_id' => $user->id]);
    Receipt::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'total_amount' => 100, 'currency' => 'NOK', 'receipt_date' => '2026-09-27']);
    Invoice::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'total_amount' => 100, 'currency' => 'NOK', 'invoice_date' => '2026-09-27']);
    $this->get(route('analytics.index'))->assertInertia(fn (Assert $page) => $page->where('tab_data.monthly_trend.0.total', 100));
});

it('converts collection summaries once using only the primary financial representation', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Http::fake(['*' => Http::response(historicalRateCsv('EUR', '2026-09-25', '12'))]);
    $file = File::factory()->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->create(['file_id' => $file->id, 'user_id' => $user->id, 'total_amount' => 10, 'currency' => 'EUR', 'receipt_date' => '2026-09-27']);
    $invoice = Invoice::factory()->create(['file_id' => $file->id, 'user_id' => $user->id, 'total_amount' => 10, 'currency' => 'EUR', 'invoice_date' => '2026-09-27']);
    ExtractableEntity::create(['extracted_at' => now(), 'user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true]);
    ExtractableEntity::create(['extracted_at' => now(), 'user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'is_primary' => false]);
    $collection = Collection::factory()->create(['user_id' => $user->id]);
    $collection->files()->attach($file);
    $stats = app(CollectionService::class)->getCollectionStats($collection);
    expect($stats['total'])->toBe(120.0)->and($stats['currency'])->toBe('NOK');
});

it('renders unavailable PDF totals and preserves original row currencies', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'total_amount' => 10, 'currency' => 'EUR']);
    $html = view('exports.receipts-pdf', [
        'receipts' => collect([$receipt->load(['merchant', 'lineItems'])]), 'from_date' => null, 'to_date' => null,
        'generated_at' => now(), 'total_amount' => null, 'currency' => 'NOK', 'total_count' => 1,
    ])->render();
    expect($html)->toContain('Conversion unavailable')->toContain('10.00 EUR');
});
