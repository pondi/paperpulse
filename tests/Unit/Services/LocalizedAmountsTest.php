<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Services\AI\Shared\AIDataNormalizer;
use App\Services\BankStatements\CsvImportService;
use App\Services\Receipt\ReceiptValidatorService;
use App\Services\Receipts\DecimalAmount;

it('preserves locale amounts and signed refunds across normalization', function (string $input, string $expected) {
    expect(DecimalAmount::parse($input))->toBe($expected)
        ->and((new ReceiptValidatorService)->sanitizeData(['totals' => ['total_amount' => $input]])['totals']['total_amount'])->toBe($expected)
        ->and(AIDataNormalizer::normalizeReceiptData(['total' => $input])['totals']['total_amount'])->toBe($expected);
})->with([
    ['-12.50', '-12.50'], ['1 234,56', '1234.56'], ['1.234,56', '1234.56'],
    ['1,234.56', '1234.56'], ['(12,50)', '-12.50'], ['0', '0'],
]);

it('rejects ambiguous and malformed amounts without producing zero', function (string $input) {
    expect(fn () => DecimalAmount::parse($input))->toThrow(InvalidArgumentException::class);
})->with(['1,234', '1.234', '1,2,3', '12-3', 'abc', '', '1 23,50']);

it('uses explicit separators to resolve locale ambiguity', function () {
    expect(DecimalAmount::parse('1,234', '.'))->toBe('1234')
        ->and(DecimalAmount::parse('1.234', ','))->toBe('1234');
});

it('keeps signed decimal strings in CSV transactions and rejects bad rows', function () {
    $service = new class(Mockery::mock(TextAnalysisContract::class)) extends CsvImportService
    {
        public function map(array $row): ?array
        {
            return $this->mapRowToTransaction($row, ['transaction_date' => 0, 'description' => 1, 'amount' => 2]);
        }
    };
    expect($service->map(['2024-12-31', 'Refund', '(1 234,56)'])['amount'])->toBe('-1234.56')
        ->and($service->map(['2024-12-31', 'Bad', '1,234']))->toBeNull();
});

it('sums localized VAT in decimal minor units', function () {
    $data = AIDataNormalizer::normalizeReceiptData(['receipt' => [
        'total' => '145,75', 'vat' => [['vat_amount' => '25,50'], ['vat_amount' => '20,25']],
    ]]);
    expect($data['totals']['tax_amount'])->toBe('45.75');
});
