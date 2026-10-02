<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia as Assert;

it('bounds entity lists and preserves filters across pages', function (string $model, string $route, string $prop, string $field): void {
    $user = User::factory()->create();
    $matches = $model::factory()->count(23)->create(['user_id' => $user->id, $field => 'PaginationMatch']);
    $model::factory()->create(['user_id' => $user->id, $field => 'Excluded']);
    $model::factory()->create([$field => 'PaginationMatch']);

    $query = ['search' => 'PaginationMatch', 'per_page' => 10];
    $first = $this->actingAs($user)->get(route($route, $query))->assertOk();
    $first->assertInertia(fn (Assert $page) => $page
        ->has($prop, 10)
        ->where('pagination.total', 23)
        ->where('filters.search', 'PaginationMatch')
    );
    $second = $this->get(route($route, $query + ['page' => 2]))->assertOk();
    $second->assertInertia(fn (Assert $page) => $page->has($prop, 10)->where('pagination.total', 23));

    $firstIds = collect($first->original->getData()['page']['props'][$prop])->pluck('id');
    $secondIds = collect($second->original->getData()['page']['props'][$prop])->pluck('id');
    expect($firstIds->intersect($secondIds))->toBeEmpty()
        ->and($firstIds->merge($secondIds)->diff($matches->pluck('id')))->toBeEmpty();
    if ($prop !== 'receipts') {
        $links = $first->original->getData()['page']['props']['pagination']['links'];
        expect(collect($links)->pluck('url')->filter()->every(fn (string $url): bool => str_contains($url, 'search=PaginationMatch')))->toBeTrue();
    }
})->with([
    [Invoice::class, 'invoices.index', 'invoices', 'invoice_number'],
    [Contract::class, 'contracts.index', 'contracts', 'contract_title'],
    [Voucher::class, 'vouchers.index', 'vouchers', 'code'],
    [Receipt::class, 'receipts.index', 'receipts', 'receipt_description'],
]);

it('rejects invalid list sizes and page numbers', function (string $route): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route($route, ['per_page' => 1000, 'page' => -1, 'sort_direction' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page', 'sort_direction']);
})->with(['invoices.index', 'contracts.index', 'vouchers.index', 'receipts.index']);

it('rejects unsupported sort and type filters', function (string $route): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route($route, ['sort' => 'user_id', 'type' => 'unsupported']))
        ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'type']);
})->with(['invoices.index', 'contracts.index', 'vouchers.index']);

it('applies invoice status and type filters before pagination', function (): void {
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['user_id' => $user->id, 'payment_status' => 'paid', 'invoice_type' => 'credit_note']);
    Invoice::factory()->create(['user_id' => $user->id, 'payment_status' => 'unpaid', 'invoice_type' => 'credit_note']);
    Invoice::factory()->create(['user_id' => $user->id, 'payment_status' => 'paid', 'invoice_type' => 'invoice']);

    $this->actingAs($user)->get(route('invoices.index', ['paymentStatus' => 'paid', 'type' => 'credit_note', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('invoices', 2)->where('pagination.total', 3));
});

it('applies contract status and type filters before pagination', function (): void {
    $user = User::factory()->create();
    Contract::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'active', 'contract_type' => 'service']);
    Contract::factory()->create(['user_id' => $user->id, 'status' => 'draft', 'contract_type' => 'service']);
    Contract::factory()->create(['user_id' => $user->id, 'status' => 'active', 'contract_type' => 'rental']);

    $this->actingAs($user)->get(route('contracts.index', ['status' => 'active', 'type' => 'service', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('contracts', 2)->where('pagination.total', 3));
});

it('filters vouchers using the user calendar date and redemption state', function (): void {
    $user = User::factory()->create();
    Voucher::factory()->count(3)->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => $user->currentDate(), 'is_redeemed' => false]);
    Voucher::factory()->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => $user->currentDate()->subDay(), 'is_redeemed' => false]);
    Voucher::factory()->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => null, 'is_redeemed' => true]);

    $this->actingAs($user)->get(route('vouchers.index', ['status' => 'active', 'type' => 'coupon', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('vouchers', 2)->where('pagination.total', 3));
});
