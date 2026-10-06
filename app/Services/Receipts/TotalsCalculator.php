<?php

namespace App\Services\Receipts;

class TotalsCalculator
{
    public static function calculate(array $items, array $data, object $parser): array
    {
        $totals = $parser->extractTotals($data);
        $calculated = 0;
        $covered = 0;
        foreach ($items as $item) {
            $value = $item['total_price'] ?? $item['total'] ?? null;
            if ($value === null && (isset($item['unit_price']) || isset($item['price']))) {
                $value = DecimalAmount::multiplyPrice($item['unit_price'] ?? $item['price'], $item['quantity'] ?? 1);
            }
            if ($value !== null) {
                $calculated += DecimalAmount::minorUnits($value);
                $covered++;
            }
        }
        $source = isset($totals['total_amount']) ? DecimalAmount::minorUnits($totals['total_amount']) : null;
        $tax = DecimalAmount::minorUnits($totals['tax_amount'] ?? 0);
        $tip = DecimalAmount::minorUnits($totals['tip_amount'] ?? 0);
        $discount = DecimalAmount::minorUnits($totals['discount_amount'] ?? 0);
        $candidates = [$calculated + $tax + $tip - $discount, $calculated + $tip - $discount];
        $matches = $source !== null && min(array_map(static fn (int $candidate): int => abs($candidate - $source), $candidates)) <= 1;

        return [
            'total_amount' => DecimalAmount::format($source ?? ($calculated + $tax + $tip - $discount)),
            'tax_amount' => DecimalAmount::format($tax),
            'tip_amount' => DecimalAmount::format($tip),
            'discount_amount' => DecimalAmount::format($discount),
            'source_total' => $source === null ? null : DecimalAmount::format($source),
            'calculated_total' => DecimalAmount::format($calculated),
            'processed_items' => $covered,
            'total_items' => count($items),
            'needs_review' => ($source !== null && $covered > 0 && ! $matches) || $covered < count($items) || ($source === null && $covered === 0),
        ];
    }
}
