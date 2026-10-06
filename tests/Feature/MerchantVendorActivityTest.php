<?php

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    Bus::fake();
    Http::preventStrayRequests();
});

it('shows vendor details and only the current owners active purchase history', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $vendor = Vendor::create(['user_id' => $user->id, 'name' => 'DigitalOcean', 'description' => 'Cloud hosting', 'contact_email' => 'support@example.test']);
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'currency' => 'NOK']);
    $foreign = Receipt::factory()->create(['user_id' => $other->id]);
    LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id, 'text' => 'Cloud subscription', 'qty' => 1, 'price' => 100]);
    LineItem::create(['receipt_id' => $foreign->id, 'vendor_id' => $vendor->id, 'text' => 'Foreign item', 'qty' => 1, 'price' => 200]);
    LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id, 'text' => 'Deleted item', 'qty' => 1, 'price' => 300])->delete();

    $this->actingAs($user)->get(route('vendors.show', $vendor))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Receipt/VendorShow')
            ->where('vendor.name', 'DigitalOcean')->where('vendor.description', 'Cloud hosting')
            ->where('vendor.contact_email', 'support@example.test')->has('items.data', 1)
            ->where('items.data.0.text', 'Cloud subscription')->where('items.data.0.receipt_id', $receipt->id));

    $receipt->delete();
    $this->get(route('vendors.show', $vendor))->assertForbidden();
});

it('excludes deleted and foreign activity from converted merchant and vendor totals', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Store', 'user_id' => $user->id]);
    $vendor = Vendor::create(['name' => 'Brand', 'user_id' => $user->id]);
    $foreignMerchant = Merchant::create(['name' => 'Foreign', 'user_id' => $other->id]);
    $foreignVendor = Vendor::create(['name' => 'Foreign', 'user_id' => $other->id]);
    $active = Receipt::factory()->create(['user_id' => $user->id, 'merchant_id' => $merchant->id, 'total_amount' => 10, 'currency' => 'EUR', 'receipt_date' => '2026-09-27']);
    $deleted = Receipt::factory()->create(['user_id' => $user->id, 'merchant_id' => $merchant->id, 'total_amount' => 1000, 'currency' => 'NOK', 'receipt_date' => '2026-09-30']);
    $foreign = Receipt::factory()->create(['user_id' => $other->id, 'merchant_id' => $foreignMerchant->id, 'total_amount' => 1000, 'currency' => 'NOK']);
    LineItem::create(['receipt_id' => $active->id, 'vendor_id' => $vendor->id, 'qty' => 2, 'price' => 3]);
    LineItem::create(['receipt_id' => $active->id, 'vendor_id' => $vendor->id, 'qty' => 100, 'price' => 100])->delete();
    LineItem::create(['receipt_id' => $deleted->id, 'vendor_id' => $vendor->id, 'qty' => 100, 'price' => 100]);
    LineItem::create(['receipt_id' => $foreign->id, 'vendor_id' => $foreignVendor->id, 'qty' => 100, 'price' => 100]);
    $deleted->delete();
    Http::fake(['*' => Http::response("BASE_CUR;QUOTE_CUR;UNIT_MULT;TIME_PERIOD;OBS_VALUE\nEUR;NOK;0;2026-09-25;12\n")]);
    $this->actingAs($user);
    $this->get(route('merchants.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('merchants.data', 1)->where('merchants.data.0.lastInvoice.amount', 120)
        ->where('merchants.data.0.lastInvoice.dateTime', '2026-09-27')->where('merchants.data.0.lastInvoice.currency', 'NOK'));
    $this->get(route('vendors.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('vendors.data', 1)->where('vendors.data.0.stats.totalValue', 72)->where('vendors.data.0.stats.totalItems', 1));
    $active->delete();
    $this->get(route('merchants.index'))->assertInertia(fn (Assert $page) => $page->has('merchants.data', 0));
    $this->get(route('vendors.index'))->assertInertia(fn (Assert $page) => $page->has('vendors.data', 0));
});

it('bounds merchant list loading', function (): void {
    $user = User::factory()->create();
    foreach (range(1, 51) as $number) {
        $merchant = Merchant::create(['user_id' => $user->id, 'name' => sprintf('Store %02d', $number)]);
        Receipt::factory()->create(['user_id' => $user->id, 'merchant_id' => $merchant->id, 'total_amount' => 1, 'currency' => 'NOK']);
    }
    $this->actingAs($user)->get(route('merchants.index'))->assertInertia(fn (Assert $page) => $page
        ->has('merchants.data', 50)->where('merchants.total', 51)->where('merchants.last_page', 2));
});
