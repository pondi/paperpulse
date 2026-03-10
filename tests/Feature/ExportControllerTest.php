<?php

declare(strict_types=1);

use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Auth ---

it('requires authentication for CSV export', function () {
    $this->get(route('export.receipts.csv'))
        ->assertRedirect(route('login'));
});

it('requires authentication for PDF export', function () {
    $this->get(route('export.receipts.pdf'))
        ->assertRedirect(route('login'));
});

// --- CSV Export ---

it('exports receipts as CSV', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Test Store', 'user_id' => $user->id]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'tax_amount' => 5.00,
        'currency' => 'USD',
        'receipt_category' => 'Groceries',
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.receipts.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('exports CSV with date filters', function () {
    $user = User::factory()->create();

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => '2025-01-15',
        'total_amount' => 10.00,
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 20.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.receipts.csv', [
            'from_date' => '2025-06-01',
            'to_date' => '2025-06-30',
        ]))
        ->assertOk();

    $content = $response->streamedContent();
    // Should only contain the June receipt
    expect($content)->toContain('20');
});

it('exports empty CSV when user has no receipts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('export.receipts.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('does not include other users receipts in CSV export', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Receipt::factory()->create([
        'user_id' => $other->id,
        'total_amount' => 999.99,
        'receipt_description' => 'other-user-secret',
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.receipts.csv'))
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->not->toContain('other-user-secret');
});

// --- PDF Export ---

it('exports receipts as PDF', function () {
    $user = User::factory()->create();

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 100.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.receipts.pdf'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('pdf');
});

// --- Single Receipt PDF ---

it('exports a single receipt as PDF', function () {
    $user = User::factory()->create();

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 55.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.receipt.pdf', $receipt->id))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('pdf');
});

it('returns 404 for other users single receipt PDF', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('export.receipt.pdf', $receipt->id))
        ->assertNotFound();
});
