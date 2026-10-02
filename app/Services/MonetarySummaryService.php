<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class MonetarySummaryService
{
    public function __construct(private HistoricalCurrencyService $currencyService) {}

    /**
     * @param  iterable<Model>  $rows
     * @param  array<string, string>  $amountFields
     * @param  array<string, callable(Model): string|int>  $groups
     * @return array{count: int, amounts: array<string, float|null>, groups: array<string, array<string|int, array{count: int, total: float|null}>>}
     */
    public function aggregate(iterable $rows, array $amountFields, string $dateField, string $currency, array $groups = []): array
    {
        $result = ['count' => 0, 'amounts' => array_fill_keys(array_keys($amountFields), 0.0), 'groups' => array_fill_keys(array_keys($groups), [])];
        foreach ($rows as $row) {
            $result['count']++;
            $amounts = [];
            foreach ($amountFields as $name => $field) {
                $amount = $row->getAttribute($field);
                $amounts[$name] = $this->currencyService->convert($amount === null ? null : (float) $amount, $row->getAttribute('currency'), $row->getAttribute($dateField), $currency);
                $result['amounts'][$name] = self::add($result['amounts'][$name], $amounts[$name]);
            }
            foreach ($groups as $name => $group) {
                $key = $group($row);
                $result['groups'][$name][$key] ??= ['count' => 0, 'total' => 0.0];
                $result['groups'][$name][$key]['count']++;
                $result['groups'][$name][$key]['total'] = self::add($result['groups'][$name][$key]['total'], $amounts['total']);
            }
        }

        return $result;
    }

    public static function add(?float $left, ?float $right): ?float
    {
        return $left === null || $right === null ? null : $left + $right;
    }
}
