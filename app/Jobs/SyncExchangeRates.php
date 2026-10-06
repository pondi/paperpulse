<?php

namespace App\Jobs;

use App\Services\NorgesBankExchangeRateService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SyncExchangeRates implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $backoff = 300;

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('norges-bank-exchange-rates'))->releaseAfter(300)->expireAfter(1860)];
    }

    public function failed(?Throwable $exception): void
    {
        report(new RuntimeException('Norges Bank exchange rate sync exhausted its retries; historical rates may be incomplete.', 0, $exception));
    }

    /**
     * Execute the job.
     */
    public function handle(NorgesBankExchangeRateService $service): void
    {
        $cache = Cache::store('database');
        $cursorKey = 'norges-bank:exchange-rates:synced-through';
        $cursor = $cache->get($cursorKey);
        $from = $cursor === null ? CarbonImmutable::parse('1960-01-01') : CarbonImmutable::parse($cursor)->subDays(7);
        $to = CarbonImmutable::now('Europe/Oslo')->startOfDay();

        while ($from->lessThanOrEqualTo($to)) {
            $end = $from->endOfYear()->min($to);
            try {
                $service->sync($from->toDateString(), $end->toDateString());
            } catch (Throwable $exception) {
                throw new RuntimeException("Norges Bank exchange rate sync failed for {$from->toDateString()} to {$end->toDateString()}.", 0, $exception);
            }
            if ($cursor === null || $end->toDateString() > $cursor) {
                $cache->forever($cursorKey, $end->toDateString());
                $cursor = $end->toDateString();
            }
            $from = $end->startOfDay()->addDay();
        }
    }
}
