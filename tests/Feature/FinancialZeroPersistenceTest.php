<?php

use App\Models\File;
use App\Services\AI\Extractors\BankStatement\BankStatementDataNormalizer;
use App\Services\AI\Extractors\Invoice\InvoiceDataNormalizer;
use App\Services\AI\Extractors\Voucher\VoucherDataNormalizer;
use App\Services\Factories\BankStatementFactory;
use App\Services\Factories\InvoiceFactory;
use App\Services\Factories\VoucherFactory;

it('persists zero balances, paid invoices and depleted vouchers', function () {
    $file = File::factory()->create();
    $statement = app(BankStatementFactory::class)->create((new BankStatementDataNormalizer)->normalize([
        'bank_name' => 'Bank', 'opening_balance' => 0, 'closing_balance' => 5,
    ]), $file);
    $invoice = app(InvoiceFactory::class)->create((new InvoiceDataNormalizer)->normalize([
        'invoice_number' => 'INV1', 'total_amount' => 50, 'amount_paid' => 50, 'amount_due' => 0,
    ]), $file);
    $voucher = app(VoucherFactory::class)->create((new VoucherDataNormalizer)->normalize([
        'value_amount' => 0,
    ]), $file);

    expect($statement->fresh()->opening_balance)->toBe('0.00')
        ->and($invoice->fresh()->amount_due)->toBe('0.00')
        ->and($voucher)->not->toBeNull()
        ->and($voucher->fresh()->original_value)->toBe('0.00')
        ->and($voucher->fresh()->is_redeemed)->toBeFalse();
});
