<?php

use App\Jobs\SyncExchangeRates;
use App\Models\ExchangeRateObservation;
use App\Services\HistoricalCurrencyService;
use App\Services\NorgesBankExchangeRateService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
    Http::preventStrayRequests();
    Cache::store('database')->forget('norges-bank:exchange-rates:synced-through');
});

function norgesBankCsv(string $rows): string
{
    return "FREQ;BASE_CUR;QUOTE_CUR;TENOR;UNIT_MULT;TIME_PERIOD;OBS_VALUE\n".$rows;
}

it('imports all currencies and updates corrected observations without duplicates', function (): void {
    Http::fakeSequence()
        ->push(norgesBankCsv("B;EUR;NOK;SP;0;2026-09-25;12\nB;JPY;NOK;SP;2;2026-09-25;6\nB;USD;NOK;SP;0;2026-09-25;\n"))
        ->push(norgesBankCsv("B;EUR;NOK;SP;0;2026-09-25;13\nB;JPY;NOK;SP;2;2026-09-25;6\n"));

    expect(app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toBe(2);
    expect((new HistoricalCurrencyService)->convert(100, 'JPY', '2026-09-27', 'EUR'))->toBe(0.5);
    expect(app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toBe(2);
    $this->assertDatabaseCount('exchange_rate_observations', 2);
    $this->assertDatabaseHas('exchange_rate_observations', ['currency' => 'EUR', 'rate' => 13, 'units' => 1]);
    $this->assertDatabaseHas('exchange_rate_observations', ['currency' => 'JPY', 'rate' => 6, 'units' => 100]);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/EXR/B..NOK.SP')
        && $request['startPeriod'] === '2026-09-25' && $request['endPeriod'] === '2026-09-27');
});

it('excludes krone indices while importing currency observations', function (string $index): void {
    Http::fake(['*' => Http::response(norgesBankCsv("B;USD;NOK;SP;0;1989-07-03;7\nB;{$index};NOK;SP;0;1989-07-03;102.58\nB;JPY;NOK;SP;2;1989-07-03;5\n"))]);

    expect(app(NorgesBankExchangeRateService::class)->sync('1989-01-01', '1989-12-31'))->toBe(2);

    $this->assertDatabaseCount('exchange_rate_observations', 2);
    $this->assertDatabaseHas('exchange_rate_observations', ['currency' => 'USD', 'rate' => 7, 'units' => 1]);
    $this->assertDatabaseHas('exchange_rate_observations', ['currency' => 'JPY', 'rate' => 5, 'units' => 100]);
    $this->assertDatabaseMissing('exchange_rate_observations', ['currency' => $index]);
})->with(['import-weighted index' => 'I44', 'trade-weighted index' => 'TWI']);

it('rejects malformed provider data without storing a partial response', function (string $row): void {
    Http::fake(['*' => Http::response(norgesBankCsv("B;EUR;NOK;SP;0;2026-09-25;12\n".$row))]);
    expect(fn () => app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('exchange_rate_observations', 0);
})->with([
    'invalid units' => ['B;JPY;NOK;SP;-1;2026-09-25;6'],
    'invalid currency code' => ['B;J12;NOK;SP;2;2026-09-25;6'],
    'wrong quote currency' => ['B;JPY;USD;SP;2;2026-09-25;6'],
    'future observation' => ['B;JPY;NOK;SP;2;2026-09-28;6'],
    'negative rate' => ['B;JPY;NOK;SP;2;2026-09-25;-6'],
    'non numeric rate' => ['B;JPY;NOK;SP;2;2026-09-25;unavailable'],
    'invalid date' => ['B;JPY;NOK;SP;2;2026-09-31;6'],
    'malformed row' => ['B;JPY;NOK'],
]);

it('fails on unexpected response formats', function (): void {
    Http::fake(['*' => Http::response('<html>Service unavailable</html>')]);
    expect(fn () => app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('exchange_rate_observations', 0);
});

it('retries transient API failures', function (): void {
    Http::fakeSequence()->push('', 503)->push(norgesBankCsv('B;EUR;NOK;SP;0;2026-09-25;12'));
    expect(app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toBe(1);
    Http::assertSentCount(2);
});

it('keeps stored observations usable during an API outage', function (): void {
    ExchangeRateObservation::query()->create([
        'provider' => 'norges-bank', 'currency' => 'EUR', 'observation_date' => '2026-09-25', 'rate' => 12, 'units' => 1,
    ]);
    Http::fake(['*' => Http::response('', 503)]);
    expect(fn () => app(NorgesBankExchangeRateService::class)->sync('2026-09-25', '2026-09-27'))->toThrow(RequestException::class);
    expect((new HistoricalCurrencyService)->convert(10, 'EUR', '2026-09-27', 'NOK'))->toBe(120.0);
    $this->assertDatabaseCount('exchange_rate_observations', 1);
    Http::assertSentCount(3);
    expect(Cache::store('database')->get('norges-bank:exchange-rates:synced-through'))->toBeNull();
});

it('accepts the documented no-data response for periods without observations', function (): void {
    Http::fake(['*' => Http::response('<message:Error><message:ErrorMessage code="100"><com:Text>No data for data query against the dataflow</com:Text></message:ErrorMessage></message:Error>', 404)]);
    expect(app(NorgesBankExchangeRateService::class)->sync('2026-09-26', '2026-09-27'))->toBe(0);
    Http::assertSentCount(1);
    $this->assertDatabaseCount('exchange_rate_observations', 0);
});

it('fails on an unrelated not found response', function (): void {
    Http::fake(['*' => Http::response('Not found', 404)]);
    expect(fn () => app(NorgesBankExchangeRateService::class)->sync('2026-09-26', '2026-09-27'))->toThrow(RequestException::class);
    Http::assertSentCount(1);
});

it('validates the date range before requesting data', function (string $from, string $to): void {
    expect(fn () => app(NorgesBankExchangeRateService::class)->sync($from, $to))->toThrow(ValidationException::class);
    Http::assertNothingSent();
})->with([
    'invalid date' => ['2026-02-30', '2026-09-27'],
    'reverse range' => ['2026-09-28', '2026-09-27'],
    'future date' => ['2026-09-25', '2026-10-07'],
]);

it('resumes an interrupted yearly backfill and then refreshes a recent overlap', function (): void {
    $this->travelTo(now()->setDate(1961, 1, 2)->startOfDay());
    Http::fakeSequence()
        ->push(norgesBankCsv('B;USD;NOK;SP;0;1960-12-30;7'))
        ->push('Not found', 404)
        ->push(norgesBankCsv('B;USD;NOK;SP;0;1960-12-30;7'))
        ->push(norgesBankCsv('B;USD;NOK;SP;0;1961-01-02;7.1'));
    expect(fn () => (new SyncExchangeRates)->handle(app(NorgesBankExchangeRateService::class)))->toThrow(RuntimeException::class, 'Norges Bank exchange rate sync failed for 1961-01-01 to 1961-01-02.');
    expect(Cache::store('database')->get('norges-bank:exchange-rates:synced-through'))->toBe('1960-12-31');
    (new SyncExchangeRates)->handle(app(NorgesBankExchangeRateService::class));
    expect(Cache::store('database')->get('norges-bank:exchange-rates:synced-through'))->toBe('1961-01-02');
    Http::assertSent(fn ($request): bool => $request['startPeriod'] === '1960-12-24');
    $this->assertDatabaseCount('exchange_rate_observations', 2);
});

it('backfills past 1989 when the provider includes krone indices', function (): void {
    $this->travelTo(now()->setDate(1990, 1, 2)->startOfDay());
    Cache::store('database')->forever('norges-bank:exchange-rates:synced-through', '1988-12-31');
    Http::fakeSequence()
        ->push(norgesBankCsv('B;USD;NOK;SP;0;1988-12-30;6.5'))
        ->push(norgesBankCsv("B;USD;NOK;SP;0;1989-07-03;7\nB;I44;NOK;SP;0;1989-07-03;102.58\n"))
        ->push(norgesBankCsv("B;USD;NOK;SP;0;1990-01-02;7.1\nB;TWI;NOK;SP;0;1990-01-02;100\n"));

    (new SyncExchangeRates)->handle(app(NorgesBankExchangeRateService::class));

    expect(Cache::store('database')->get('norges-bank:exchange-rates:synced-through'))->toBe('1990-01-02');
    $this->assertDatabaseCount('exchange_rate_observations', 3);
    expect(ExchangeRateObservation::query()->distinct()->pluck('currency')->all())->toBe(['USD']);
    Http::assertSentCount(3);
});

it('refreshes from the sync cursor rather than skipping history based on a single stored rate', function (): void {
    Cache::store('database')->forever('norges-bank:exchange-rates:synced-through', '2026-09-25');
    Http::fake(['*' => Http::response(norgesBankCsv('B;EUR;NOK;SP;0;2026-10-05;12'))]);
    (new SyncExchangeRates)->handle(app(NorgesBankExchangeRateService::class));
    Http::assertSent(fn ($request): bool => $request['startPeriod'] === '2026-09-18' && $request['endPeriod'] === '2026-10-06');
    expect(Cache::store('database')->get('norges-bank:exchange-rates:synced-through'))->toBe('2026-10-06');
});

it('automatically queues the daily refresh after publication in Oslo', function (): void {
    Queue::fake();
    $event = collect(Schedule::events())->first(fn ($event): bool => $event->description === 'sync-exchange-rates');
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 17 * * *')
        ->and($event->timezone)->toBe('Europe/Oslo')
        ->and($event->withoutOverlapping)->toBeTrue();
    $event->run(app());
    Queue::assertPushed(SyncExchangeRates::class);
});

it('reports an exhausted sync to the exception handler used by Nightwatch', function (): void {
    $failure = new RuntimeException('Provider unavailable');
    $this->mock(ExceptionHandler::class)->shouldReceive('report')->once()->with(Mockery::on(
        fn (Throwable $exception): bool => str_contains($exception->getMessage(), 'Norges Bank exchange rate sync exhausted its retries')
            && $exception->getPrevious() === $failure,
    ));

    (new SyncExchangeRates)->failed($failure);
});
