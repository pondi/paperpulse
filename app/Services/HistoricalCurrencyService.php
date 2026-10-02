<?php

namespace App\Services;

use App\Models\ExchangeRateObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

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
        $cache = Cache::store('database');
        $cacheKey = 'historical-exchange-rate:v1:'.$key;
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $this->rates[$key] = $cached['rate'];
        }
        $rate = $this->fetch($currency, $day);
        $cache->put($cacheKey, ['rate' => $rate], $rate === null ? now()->addHour() : now()->addYears(10));

        return $this->rates[$key] = $rate;
    }

    private function fetch(string $currency, string $day): ?float
    {
        try {
            $response = Http::connectTimeout(3)->timeout(10)->get('https://data.norges-bank.no/api/data/EXR/B.'.$currency.'.NOK.SP', [
                'format' => 'csv', 'endPeriod' => $day, 'lastNObservations' => 1, 'locale' => 'en',
            ])->throw();
            $lines = preg_split('/\r?\n/', trim($response->body()));
            $headers = str_getcsv(array_shift($lines), ';', '"', '');
            $values = str_getcsv(array_shift($lines) ?? '', ';', '"', '');
            if (count($headers) !== count($values)) {
                return null;
            }
            $row = array_combine($headers, $values);
            if (($row['BASE_CUR'] ?? null) !== $currency || ($row['QUOTE_CUR'] ?? null) !== 'NOK'
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['TIME_PERIOD'] ?? '') || $row['TIME_PERIOD'] > $day
                || ! is_numeric($row['OBS_VALUE'] ?? null) || (float) $row['OBS_VALUE'] <= 0
                || ! in_array($row['UNIT_MULT'] ?? null, ['0', '1', '2', '3'], true)) {
                return null;
            }
            $units = 10 ** (int) $row['UNIT_MULT'];
            $observation = ExchangeRateObservation::query()->firstOrCreate([
                'provider' => 'norges-bank', 'currency' => $currency, 'observation_date' => $row['TIME_PERIOD'],
            ], ['rate' => $row['OBS_VALUE'], 'units' => $units]);

            return (float) $observation->rate / $observation->units;
        } catch (Throwable) {
            return null;
        }
    }
}
