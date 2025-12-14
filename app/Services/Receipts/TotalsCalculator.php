<?php

namespace App\Services\Receipts;

use Illuminate\Support\Facades\Log;

class TotalsCalculator
{
    public static function calculate(array $items, array $data, $parser): array
    {
        [$calculatedTotal, $validItemsCount, $itemValidationErrors] = self::calculateLineItemTotals($items);

        $aiTotals = $parser->extractTotals($data);
        $aiTotal = (float) ($aiTotals['total_amount'] ?? 0);
        $aiTax = (float) ($aiTotals['tax_amount'] ?? 0);

        self::logItemValidationErrors($itemValidationErrors, $items, $validItemsCount);

        return self::decideTotalsSource($calculatedTotal, $validItemsCount, $aiTotal, $aiTax, $items);
    }

    private static function calculateLineItemTotals(array $items): array
    {
        $calculatedTotal = 0.0;
        $validItemsCount = 0;
        $itemValidationErrors = [];

        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $itemTotal = (float) ($item['total_price'] ?? $item['total'] ?? 0);

            if ($itemTotal == 0 && $unitPrice > 0) {
                $itemTotal = $quantity * $unitPrice;
            }

            $expectedTotal = $quantity * $unitPrice;
            if ($unitPrice > 0 && abs($itemTotal - $expectedTotal) > 0.01) {
                $itemValidationErrors[] = [
                    'item_index' => $index,
                    'item_name' => $item['name'] ?? $item['description'] ?? 'Unknown',
                    'expected_total' => $expectedTotal,
                    'actual_total' => $itemTotal,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            if ($itemTotal > 0) {
                $calculatedTotal += $itemTotal;
                $validItemsCount++;
            }
        }

        return [$calculatedTotal, $validItemsCount, $itemValidationErrors];
    }

    private static function logItemValidationErrors(array $itemValidationErrors, array $items, int $validItemsCount): void
    {
        if (empty($itemValidationErrors)) {
            return;
        }

        Log::warning('[ReceiptAnalysis] Line item calculation mismatches found', [
            'validation_errors' => $itemValidationErrors,
            'total_items' => count($items),
            'valid_items' => $validItemsCount,
        ]);
    }

    private static function decideTotalsSource(
        float $calculatedTotal,
        int $validItemsCount,
        float $aiTotal,
        float $aiTax,
        array $items
    ): array {
        $tolerance = 0.02;

        if ($calculatedTotal > 0 && $validItemsCount > 0) {
            $difference = abs($calculatedTotal - $aiTotal);
            $percentDifference = $aiTotal > 0 ? ($difference / $aiTotal) : 0;

            if ($percentDifference <= $tolerance) {
                Log::info('[ReceiptAnalysis] Using AI total - close match with calculated items', [
                    'calculated_total' => $calculatedTotal,
                    'ai_total' => $aiTotal,
                    'difference' => $difference,
                    'percent_difference' => $percentDifference * 100,
                    'valid_items_count' => $validItemsCount,
                ]);

                return ['total_amount' => $aiTotal, 'tax_amount' => $aiTax];
            }

            Log::warning('[ReceiptAnalysis] Significant difference between calculated and AI totals', [
                'calculated_total' => $calculatedTotal,
                'ai_total' => $aiTotal,
                'difference' => $difference,
                'percent_difference' => $percentDifference * 100,
                'using' => 'calculated_total',
                'valid_items_count' => $validItemsCount,
            ]);

            return ['total_amount' => $calculatedTotal, 'tax_amount' => $aiTax];
        }

        Log::warning('[ReceiptAnalysis] Using AI totals - insufficient line item data', [
            'calculated_total' => $calculatedTotal,
            'ai_total' => $aiTotal,
            'valid_items_count' => $validItemsCount,
            'total_items' => count($items),
        ]);

        return ['total_amount' => $aiTotal, 'tax_amount' => $aiTax];
    }
}
