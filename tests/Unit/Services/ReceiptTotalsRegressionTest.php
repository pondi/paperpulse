<?php

use App\Services\AI\Extractors\Receipt\ReceiptDataNormalizer;
use App\Services\Receipt\ReceiptParserService;
use App\Services\Receipts\TotalsCalculator;

it('preserves authoritative source totals and includes refunds', function (array $items, array $totals, string $expected, bool $review) {
    $parser = new class($totals)
    {
        public function __construct(private array $totals) {}

        public function extractTotals(array $data): array
        {
            return $this->totals;
        }
    };
    $result = TotalsCalculator::calculate($items, [], $parser);

    expect($result['total_amount'])->toBe($expected)
        ->and($result['needs_review'])->toBe($review);
})->with([
    [[['total_price' => 10]], [], '10.00', false],
    [[['total_price' => 10]], ['total_amount' => 0], '0.00', true],
    [[['total_price' => 100], ['total_price' => -20]], [], '80.00', false],
    [[['total_price' => -20]], ['total_amount' => -20], '-20.00', false],
    [[['total_price' => 20]], ['total_amount' => 100], '100.00', true],
    [[['total_price' => 100]], ['total_amount' => 125, 'tax_amount' => 25], '125.00', false],
    [[['total_price' => 100]], ['total_amount' => 110, 'tip_amount' => 10], '110.00', false],
    [[['total_price' => '0.10'], ['total_price' => '0.20']], [], '0.30', false],
]);

it('preserves missing totals through the actual parser', function () {
    $parser = app(ReceiptParserService::class);
    expect($parser->extractTotals([])['total_amount'])->toBeNull()
        ->and(TotalsCalculator::calculate([['total' => 10]], [], $parser)['total_amount'])->toBe('10.00');
});

it('reconciles nested receipt VAT without discarding tax', function () {
    $parser = app(ReceiptParserService::class);
    $data = ['receipt' => ['total' => '125,50', 'vat' => [['vat_amount' => '25,50']]]];
    expect($parser->extractTotals($data)['tax_amount'])->toBe('25.50')
        ->and(TotalsCalculator::calculate([['total_price' => 100]], $data, $parser)['needs_review'])->toBeFalse();
});

it('reconciles source discounts through normalized extraction and preserves inconsistencies', function (string $total, bool $review): void {
    $data = app(ReceiptDataNormalizer::class)->normalize([
        'items' => [['total_price' => '14.80'], ['total_price' => '7.90']],
        'total_amount' => $total, 'total_discount' => '1.14',
    ]);
    $totals = TotalsCalculator::calculate($data['items'], $data, app(ReceiptParserService::class));
    expect($totals['calculated_total'])->toBe('22.70')->and($totals['discount_amount'])->toBe('1.14')
        ->and($totals['total_amount'])->toBe($total)->and($totals['needs_review'])->toBe($review);
})->with([['21.56', false], ['21.57', false], ['22.70', true], ['20.00', true]]);
