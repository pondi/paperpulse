<?php

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use Laravel\Dusk\Browser;

test('receipt index loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitFor('h2')
            ->assertPathIs('/receipts');
    });
});

test('receipt index shows empty state when no receipts exist', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitForText('Upload your first receipts')
            ->assertSee('Upload your first receipts')
            ->assertSee('Upload receipts');
    });
});

test('receipt index shows receipts when they exist', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Dusk Test Store',
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 149.99,
        'currency' => 'USD',
        'receipt_date' => '2024-06-15',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitForText('Dusk Test Store')
            ->assertSee('Dusk Test Store');
    });
});

test('clicking receipt row opens drawer', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Drawer Test Store',
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 42.00,
        'currency' => 'USD',
        'receipt_date' => '2024-05-20',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitForText('Drawer Test Store')
            ->pause(500)
            // Click the merchant name cell (which triggers openReceipt)
            ->click('td.px-6.py-4.whitespace-nowrap:nth-child(3)')
            ->pause(700)
            // The drawer should show the merchant name
            ->waitFor('.w-screen.sm\\:w-\\[500px\\]')
            ->assertSee('Drawer Test Store')
            ->assertSee('Share');
    });
});

test('receipt show page loads', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Show Page Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 75.50,
        'currency' => 'USD',
        'receipt_date' => '2024-03-10',
        'receipt_description' => 'Test receipt description',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Show Page Store')
            ->assertSee('Show Page Store')
            ->assertPathIs('/receipts/' . $receipt->id);
    });
});

test('receipt show has edit button and toggles edit mode', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Edit Toggle Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 50.00,
        'currency' => 'USD',
        'receipt_date' => '2024-02-01',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Edit Toggle Store')
            ->assertSee('Edit Receipt')
            ->pause(300)
            ->press('Edit Receipt')
            ->pause(500)
            ->assertSee('Save Changes')
            ->assertPresent('input[type="number"]');
    });
});

test('receipt show displays tags section', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Tags Section Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 30.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Tags Section Store')
            ->assertSee('Tags');
    });
});

test('receipt show displays line items section', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Line Items Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 45.00,
        'currency' => 'USD',
    ]);

    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Widget Alpha',
        'sku' => 'WA-001',
        'qty' => 2,
        'price' => 15.00,
        'total' => 30.00,
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Line Items Store')
            ->assertSee('Line Items')
            ->assertSee('Widget Alpha')
            ->assertSee('WA-001');
    });
});

test('receipt show displays collections section', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Collections Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 60.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Collections Store')
            ->assertSee('Collections');
    });
});

test('receipt show has breadcrumbs', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Breadcrumb Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 20.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Breadcrumb Store')
            ->assertSee('Dashboard')
            ->assertSee('Receipts');
    });
});

test('receipt show has back to overview link', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Back Link Store',
    ]);

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 20.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/' . $receipt->id)
            ->waitForText('Back Link Store')
            ->assertPresent('a[href$="/receipts"]');
    });
});

test('bulk select shows operations bar', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Bulk Select Store',
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 25.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitForText('Bulk Select Store')
            ->pause(500)
            ->click('thead input[type="checkbox"]')
            ->pause(500)
            ->waitForText('selected')
            ->assertSee('selected')
            ->assertSee('Categorize')
            ->assertSee('Export');
    });
});
