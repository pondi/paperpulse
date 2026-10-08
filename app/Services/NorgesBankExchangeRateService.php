<?php

namespace App\Services;

use App\Models\ExchangeRateObservation;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class NorgesBankExchangeRateService
{
    private const array KRONE_INDEX_SERIES = ['I44', 'TWI'];

    public function sync(string $from, string $to): int
    {
        Validator::make(['from' => $from, 'to' => $to], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ])->validate();

        $response = Http::connectTimeout(10)->timeout(90)->retry(3, 500,
            fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)),
            throw: false)
            ->get('https://data.norges-bank.no/api/data/EXR/B..NOK.SP', [
                'format' => 'csv', 'startPeriod' => $from, 'endPeriod' => $to, 'locale' => 'en',
            ]);
        if ($response->notFound() && str_contains($response->body(), 'ErrorMessage code="100"')
            && str_contains($response->body(), 'No data for data query')) {
            return 0;
        }
        $response->throw();

        $lines = preg_split('/\r\n|\n|\r/', trim($response->body()));
        $headers = str_getcsv(ltrim(array_shift($lines), "\xEF\xBB\xBF"), ';', '"', '');
        if (array_diff(['BASE_CUR', 'QUOTE_CUR', 'UNIT_MULT', 'TIME_PERIOD', 'OBS_VALUE'], $headers) !== []) {
            throw new RuntimeException('Norges Bank returned an unexpected exchange rate format.');
        }

        $observations = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, ';', '"', '');
            if (count($values) !== count($headers)) {
                throw new RuntimeException('Norges Bank returned a malformed exchange rate row.');
            }
            $row = array_combine($headers, $values);
            if (in_array($row['BASE_CUR'], self::KRONE_INDEX_SERIES, true) || $row['OBS_VALUE'] === '') {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $row['TIME_PERIOD']);
            if (! preg_match('/^[A-Z]{3}$/', $row['BASE_CUR']) || $row['QUOTE_CUR'] !== 'NOK'
                || ($row['FREQ'] ?? 'B') !== 'B' || ($row['TENOR'] ?? 'SP') !== 'SP'
                || $date === false || $date->format('Y-m-d') !== $row['TIME_PERIOD']
                || $row['TIME_PERIOD'] < $from || $row['TIME_PERIOD'] > $to
                || ! is_numeric($row['OBS_VALUE']) || ! is_finite((float) $row['OBS_VALUE']) || (float) $row['OBS_VALUE'] <= 0
                || ! in_array($row['UNIT_MULT'], ['0', '1', '2', '3', '4', '5', '6'], true)) {
                throw new RuntimeException('Norges Bank returned an invalid exchange rate observation.');
            }
            $observations[] = [
                'provider' => 'norges-bank', 'currency' => $row['BASE_CUR'],
                'observation_date' => $row['TIME_PERIOD'], 'rate' => $row['OBS_VALUE'],
                'units' => 10 ** (int) $row['UNIT_MULT'],
            ];
        }

        ExchangeRateObservation::resolveConnection()->transaction(function () use ($observations): void {
            foreach (array_chunk($observations, 500) as $chunk) {
                ExchangeRateObservation::query()->upsert($chunk, ['provider', 'currency', 'observation_date'], ['rate', 'units']);
            }
        });

        return count($observations);
    }
}
