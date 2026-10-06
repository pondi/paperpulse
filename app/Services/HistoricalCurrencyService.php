<?php

namespace App\Services;

use App\Models\ExchangeRateObservation;
use Carbon\CarbonInterface;

class HistoricalCurrencyService
{
    /** @var array<string, float|null> */
    private array $rates = [];

    public function convert(?float $amount, ?string $source, CarbonInterface|string|null $date, string $target): ?float
    {
        if ($amount === null || ! preg_match('/^[A-Z]{3}$/', $source ?? '') || ! preg_match('/^[A-Z]{3}$/', $target)) {
            return null;
        }
        if ($source === $target) {
            return $amount;
        }
        if ($date === null) {
            return null;
        }
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);
        $sourceRate = $this->rate($source, $day);
        $targetRate = $this->rate($target, $day);

        return $sourceRate !== null && $targetRate !== null ? $amount * $sourceRate / $targetRate : null;
    }

    private function rate(string $currency, string $day): ?float
    {
        if ($currency === 'NOK') {
            return 1.0;
        }
        $key = $currency.':'.$day;
        if (array_key_exists($key, $this->rates)) {
            return $this->rates[$key];
        }
        $observation = ExchangeRateObservation::query()
            ->where('provider', 'norges-bank')->where('currency', $currency)
            ->where('observation_date', '<=', $day)
            ->orderByDesc('observation_date')->first();

        return $this->rates[$key] = $observation !== null && $observation->units > 0 && (float) $observation->rate > 0
            ? (float) $observation->rate / $observation->units : null;
    }
}
