<?php

declare(strict_types=1);

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for bulk CSV export', function () {
    $this->post(route('bulk.receipts.export.csv'), ['receipt_ids' => [1]])
        ->assertRedirect(route('login'));
});

it('exports selected receipts as CSV with chunking', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Bulk Store', 'user_id' => $user->id]);

    $receipts = Receipt::factory()->count(3)->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 25.00,
        'tax_amount' => 3.00,
        'currency' => 'USD',
        'receipt_category' => 'Groceries',
        'receipt_description' => 'bulk-test-item',
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => $receipts->pluck('id')->toArray(),
        ])
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Receipt Date');
    expect($content)->toContain('Bulk Store');
    expect($content)->toContain('bulk-test-item');
});

it('includes line items in bulk CSV export', function () {
    $user = User::factory()->create();

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'total_amount' => 50.00,
    ]);

    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Widget Alpha',
        'qty' => 2,
        'price' => 25.00,
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$receipt->id],
        ])
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('Widget Alpha');
    expect($content)->toContain('Qty: 2');
});

it('does not include other users receipts in bulk CSV export', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownReceipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_description' => 'my-receipt',
    ]);

    $otherReceipt = Receipt::factory()->create([
        'user_id' => $other->id,
        'receipt_description' => 'secret-receipt',
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$ownReceipt->id, $otherReceipt->id],
        ])
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('my-receipt');
    expect($content)->not->toContain('secret-receipt');
});

it('does not hardcode currency in CSV export', function () {
    $user = User::factory()->create();

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$receipt->id],
        ])
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('EUR');
    expect($content)->not->toContain('NOK');
});
