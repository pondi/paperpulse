<?php

use App\Services\AI\Extractors\BankStatement\BankStatementDataNormalizer;
use App\Services\AI\Extractors\Invoice\InvoiceDataNormalizer;
use App\Services\AI\Extractors\Voucher\VoucherDataNormalizer;

it('keeps zero values in typed financial normalization', function (object $normalizer, array $input, string $section, string $field) {
    expect($normalizer->normalize($input)[$section][$field] ?? null)->toBe(0);
})->with([
    [new BankStatementDataNormalizer, ['opening_balance' => 0], 'balances', 'opening_balance'],
    [new InvoiceDataNormalizer, ['amount_due' => 0], 'totals', 'amount_due'],
    [new VoucherDataNormalizer, ['value_amount' => 0], 'value', 'amount'],
]);

it('removes only absent values from financial sections', function () {
    $normalized = (new InvoiceDataNormalizer)->normalize(['amount_due' => false, 'notes' => '', 'vendor_name' => null]);

    expect($normalized['totals']['amount_due'])->toBeFalse()
        ->and($normalized['vendor'])->toBe([])
        ->and($normalized['notes'])->toBe('');
});
