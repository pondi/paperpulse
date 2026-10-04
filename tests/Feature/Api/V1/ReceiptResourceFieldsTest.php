<?php

use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('always includes tags and line_items fields in receipt response', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $merchant = Merchant::create(['name' => 'Test Store', 'vat_number' => 'NO123456789MVA']);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    $receipt = Receipt::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 100.00,
        'tax_amount' => 10.00,
        'currency' => 'USD',
        'receipt_date' => now(),
        'receipt_description' => 'Test receipt',
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'receipt',
        'entity_id' => $receipt->id,
        'is_primary' => true,
        'extracted_at' => now(),
    ]);

    $receipt->lineItems()->create(['text' => 'Coffee', 'qty' => 2, 'price' => 25, 'total' => 50]);
    expect($receipt->toSearchableArray()['merchant_vat_id'])->toBe('NO123456789MVA');

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertOk();

    // Verify that tags and line_items fields are ALWAYS present
    $json = $response->json();

    expect($json)->toHaveKey('data.receipt.tags');
    expect($json)->toHaveKey('data.receipt.line_items');
    expect($json)->toHaveKey('data.receipt.category');
    expect($json)->toHaveKey('data.receipt.merchant');

    // Verify they are arrays/null as expected
    expect($json['data']['receipt']['tags'])->toBeArray();
    expect($json['data']['receipt']['line_items'])->toHaveCount(1);
    expect($json['data']['receipt']['summary'])->toBe('Test receipt');
    expect((float) $json['data']['receipt']['line_items'][0]['amount'])->toBe(50.0);
    expect((float) $json['data']['receipt']['line_items'][0]['unit_price'])->toBe(25.0);
    expect($json['data']['receipt']['category'])->toBeNull();

    // Verify merchant is an object since it's loaded
    expect($json['data']['receipt']['merchant'])->toBeArray();
    expect($json['data']['receipt']['merchant']['name'])->toBe('Test Store');
});

it('merchant field is always present even when null', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    // Create a receipt without a merchant
    $receipt = Receipt::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'merchant_id' => null,
        'total_amount' => 100.00,
        'tax_amount' => 10.00,
        'currency' => 'USD',
        'receipt_date' => now(),
        'receipt_description' => 'Test receipt without merchant',
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'receipt',
        'entity_id' => $receipt->id,
        'is_primary' => true,
        'extracted_at' => now(),
    ]);

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertOk();

    $json = $response->json();

    // Verify that merchant field is present (even if null)
    expect($json)->toHaveKey('data.receipt.merchant');
    expect($json['data']['receipt']['merchant'])->toBeNull();
});
