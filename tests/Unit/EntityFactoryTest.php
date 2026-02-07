<?php

use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Services\EntityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('skips entities without data', function () {
    $file = File::factory()->create();
    $factory = app(EntityFactory::class);

    $created = $factory->createEntitiesFromParsedData([
        'entities' => [
            ['type' => 'voucher', 'data' => []],
        ],
    ], $file, 'receipt');

    expect($created)->toHaveCount(0);
    $this->assertDatabaseMissing('vouchers', ['file_id' => $file->id]);
});

it('resolves merchant for voucher', function () {
    $file = File::factory()->create();
    $factory = app(EntityFactory::class);

    $created = $factory->createEntitiesFromParsedData([
        'entities' => [
            [
                'type' => 'voucher',
                'data' => [
                    'voucher_type' => 'gift_card',
                    'code' => 'TEST-123',
                    'original_value' => 100,
                    'merchant' => [
                        'name' => 'Acme Shop',
                        'vat_number' => 'NO123456789',
                    ],
                ],
            ],
        ],
    ], $file, 'receipt');

    expect($created)->toHaveCount(1);
    $voucher = $created[0]['model'];
    $merchant = Merchant::first();

    expect($merchant)->not->toBeNull();
    expect($voucher->merchant_id)->toBe($merchant->id);
    expect($merchant->name)->toBe('Acme Shop');
});

it('repairs flattened structure and normalizes case', function () {
    $file = File::factory()->create();
    $factory = app(EntityFactory::class);

    // Simulate malformed Gemini response: Uppercase type and flattened data (no 'data' key)
    $created = $factory->createEntitiesFromParsedData([
        'entities' => [
            [
                'type' => 'RECEIPT', // Uppercase
                'confidence_score' => 0.95,
                // Flattened fields (should be in 'data')
                'merchant' => ['name' => 'Test Store'],
                'totals' => ['total_amount' => 500],
                'receipt_info' => ['date' => '2025-01-01'],
            ],
        ],
    ], $file, 'receipt');

    expect($created)->toHaveCount(1);
    $receipt = $created[0]['model'];

    expect($created[0]['type'])->toBe('receipt');
    expect((float) $receipt->total_amount)->toBe(500.00);
});

it('maps nested invoice normalizer data to flat columns', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'processing_type' => 'gemini',
    ]);

    $parsedData = [
        'entities' => [
            [
                'type' => 'invoice',
                'confidence_score' => 0.95,
                'data' => [
                    'vendor' => [
                        'name' => 'Acme Corp',
                        'address' => '123 Main St',
                        'vat_number' => 'NO123456789',
                        'email' => 'billing@acme.com',
                        'phone' => '+47 123 45 678',
                    ],
                    'customer' => [
                        'name' => 'Best Co',
                        'address' => '456 Oak Ave',
                        'vat_number' => 'NO987654321',
                        'email' => 'accounts@best.co',
                        'phone' => '+47 987 65 432',
                    ],
                    'invoice_info' => [
                        'invoice_number' => 'INV-2025-001',
                        'invoice_type' => 'standard',
                        'invoice_date' => '2025-06-15',
                        'due_date' => '2025-07-15',
                        'delivery_date' => '2025-06-10',
                        'purchase_order_number' => 'PO-1234',
                        'reference_number' => 'REF-5678',
                    ],
                    'totals' => [
                        'subtotal' => 1000.00,
                        'tax_amount' => 250.00,
                        'discount_amount' => 50.00,
                        'shipping_amount' => 25.00,
                        'total_amount' => 1225.00,
                        'amount_paid' => 0,
                        'amount_due' => 1225.00,
                    ],
                    'payment' => [
                        'method' => 'bank_transfer',
                        'status' => 'unpaid',
                        'terms' => 'Net 30',
                        'currency' => 'NOK',
                    ],
                    'line_items' => [
                        [
                            'description' => 'Widget A',
                            'quantity' => 10,
                            'unit_price' => 50.00,
                            'total_amount' => 500.00,
                        ],
                        [
                            'description' => 'Widget B',
                            'quantity' => 5,
                            'unit_price' => 100.00,
                            'total_amount' => 500.00,
                        ],
                    ],
                    'notes' => 'Please pay within 30 days',
                ],
            ],
        ],
    ];

    $factory = app(EntityFactory::class);
    $created = $factory->createEntitiesFromParsedData($parsedData, $file, 'invoice');

    expect($created)->toHaveCount(1);
    expect($created[0]['type'])->toBe('invoice');

    $invoice = $created[0]['model'];
    expect($invoice)->toBeInstanceOf(Invoice::class);

    // Verify flat columns are populated from nested data
    expect($invoice->invoice_number)->toBe('INV-2025-001');
    expect($invoice->invoice_type)->toBe('standard');
    expect($invoice->from_name)->toBe('Acme Corp');
    expect($invoice->from_address)->toBe('123 Main St');
    expect($invoice->from_vat_number)->toBe('NO123456789');
    expect($invoice->from_email)->toBe('billing@acme.com');
    expect($invoice->from_phone)->toBe('+47 123 45 678');
    expect($invoice->to_name)->toBe('Best Co');
    expect($invoice->to_address)->toBe('456 Oak Ave');
    expect($invoice->to_vat_number)->toBe('NO987654321');
    expect($invoice->to_email)->toBe('accounts@best.co');
    expect($invoice->to_phone)->toBe('+47 987 65 432');
    expect($invoice->invoice_date->format('Y-m-d'))->toBe('2025-06-15');
    expect($invoice->due_date->format('Y-m-d'))->toBe('2025-07-15');
    expect($invoice->delivery_date->format('Y-m-d'))->toBe('2025-06-10');
    expect((float) $invoice->subtotal)->toBe(1000.00);
    expect((float) $invoice->tax_amount)->toBe(250.00);
    expect((float) $invoice->discount_amount)->toBe(50.00);
    expect((float) $invoice->shipping_amount)->toBe(25.00);
    expect((float) $invoice->total_amount)->toBe(1225.00);
    expect((float) $invoice->amount_paid)->toBe(0.00);
    expect((float) $invoice->amount_due)->toBe(1225.00);
    expect($invoice->currency)->toBe('NOK');
    expect($invoice->payment_method)->toBe('bank_transfer');
    expect($invoice->payment_status)->toBe('unpaid');
    expect($invoice->payment_terms)->toBe('Net 30');
    expect($invoice->purchase_order_number)->toBe('PO-1234');
    expect($invoice->reference_number)->toBe('REF-5678');
    expect($invoice->notes)->toBe('Please pay within 30 days');

    // Verify line items were created
    $lineItems = $invoice->lineItems;
    expect($lineItems)->toHaveCount(2);
    expect($lineItems[0]->description)->toBe('Widget A');
    expect((float) $lineItems[0]->quantity)->toBe(10.00);
    expect($lineItems[1]->description)->toBe('Widget B');
    expect((float) $lineItems[1]->quantity)->toBe(5.00);
});
