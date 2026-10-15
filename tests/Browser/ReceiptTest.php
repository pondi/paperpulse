<?php

use App\Models\Category;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;

test('receipt index loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitFor('h1')
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

    Receipt::factory()->for(File::factory()->for($user))->create([
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

test('receipt row opens an accessible preview with line items and sharing', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Preview Test Store']);
    $file = File::factory()->for($user)->create(['file_type' => 'receipt', 'status' => 'completed']);
    $receipt = Receipt::factory()->for($user)->create(['file_id' => $file->id, 'merchant_id' => $merchant->id, 'total_amount' => 42, 'currency' => 'USD', 'receipt_description' => 'Keep this complete purchase description']);
    ExtractableEntity::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true]);
    LineItem::create(['receipt_id' => $receipt->id, 'text' => 'Preview purchase', 'sku' => 'SKU-013', 'qty' => 1, 'price' => 42, 'total' => 42]);
    $this->browse(function (Browser $browser) use ($user, $receipt): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/receipts')->waitFor('@receipt-preview-'.$receipt->id)
            ->keys('@receipt-preview-'.$receipt->id, WebDriverKeys::ENTER)->waitFor('[role="dialog"]')
            ->waitForText('Document information')->assertSee('Sharing')->assertSee('Keep this complete purchase description');
        $browser->script("Array.from(document.querySelectorAll('[role=dialog] summary')).find(el => el.textContent.includes('Line items')).click()");
        $browser->waitForText('SKU-013')->assertSee('Preview purchase')->press('Edit details')
            ->keys('button[aria-label="Close preview"]', WebDriverKeys::ESCAPE)->waitUntilMissing('[role="dialog"]')
            ->waitUntil('document.activeElement.getAttribute("dusk") === "receipt-preview-'.$receipt->id.'"');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->screenshot('redesign-receipts-'.$width)->click('@receipt-preview-'.$receipt->id)
                ->waitForText('Document information')->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->screenshot('redesign-receipt-preview-'.$width)->click('button[aria-label="Close preview"]')->waitUntilMissing('[role="dialog"]');
        }
        $browser->resize(1440, 1000);
        $browser->script("document.documentElement.classList.add('dark')");
        $browser->screenshot('redesign-receipts-dark');
    });
});

it('keeps receipt scope through pagination and supports confirmed bulk actions and exports', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Scoped Store']);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Travel', 'slug' => 'travel']);
    $receipts = Receipt::factory()->count(21)->for($user)->state(fn () => ['file_id' => File::factory()->for($user)->create(['file_type' => 'receipt', 'status' => 'completed'])->id])->create(['merchant_id' => $merchant->id, 'receipt_description' => 'Scoped purchase']);
    $this->browse(function (Browser $browser) use ($user, $merchant, $category, $receipts): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/receipts/merchant/'.$merchant->id)->waitFor('#receipt-search')
            ->type('#receipt-search', 'Scoped')->press('Search')->waitUntil("location.search.includes('search=Scoped')")
            ->check('thead input')->assertSee('20 receipts selected')
            ->click('nav[aria-label="Pagination"] a[href*="page=2"]')->waitForText('Page 2 of 2')
            ->assertMissing('[aria-label="Selected receipt actions"]')->check('thead input')->assertSee('1 receipt selected');
        $browser->script('window.exportContents = []; const create = URL.createObjectURL.bind(URL); URL.createObjectURL = blob => { blob.text().then(text => window.exportContents.push(text)); return create(blob); }');
        $browser->press('Export')->press('Export as CSV')->waitForText('CSV download started.')
            ->waitUntil('window.exportContents.length === 1')->assertScript("window.exportContents[0].includes('Scoped Store')", true)
            ->press('Export')->press('Export as PDF')->waitForText('PDF download started.', 20)
            ->waitUntil('window.exportContents.length === 2')->assertScript("window.exportContents[1].startsWith('%PDF-')", true)
            ->press('Categorize')->waitFor('#bulk-receipt-category')->select('#bulk-receipt-category', $category->id)
            ->press('Apply Category')->waitUntilMissing('[role="dialog"]')->waitUntilMissing('[aria-label="Selected receipt actions"]')->assertSee('Travel');
        expect(Receipt::whereIn('id', $receipts->modelKeys())->where('category_id', $category->id)->count())->toBe(1);
        $browser->check('thead input')->press('Delete')->waitFor('[role="dialog"]')->press('Cancel')->waitUntilMissing('[role="dialog"]');
        expect(Receipt::whereIn('id', $receipts->modelKeys())->count())->toBe(21);
        $browser->press('Delete')->waitFor('[role="dialog"]')->click('[role="dialog"] button[type="submit"]')
            ->waitUntilMissing('[role="dialog"]');
        expect(Receipt::whereIn('id', $receipts->modelKeys())->count())->toBe(20);
        $browser->clickLink('All Receipts')->waitForLocation('/receipts')->assertInputValue('#receipt-search', '');
    });
});

test('receipt show page loads', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Show Page Store',
    ]);

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 75.50,
        'currency' => 'USD',
        'receipt_date' => '2024-03-10',
        'receipt_description' => 'Test receipt description',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
            ->waitForText('Show Page Store')
            ->assertSee('Show Page Store')
            ->assertPathIs('/receipts/'.$receipt->id);
    });
});

test('receipt show has edit button and toggles edit mode', function () {
    $user = $this->createUser();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Edit Toggle Store',
    ]);

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 50.00,
        'currency' => 'USD',
        'receipt_date' => '2024-02-01',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
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

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 30.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
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

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
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

        $browser->visit('/receipts/'.$receipt->id)
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

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 60.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
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

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 20.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
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

    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 20.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts/'.$receipt->id)
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

    Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'total_amount' => 25.00,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/receipts')
            ->waitForText('Bulk Select Store')
            ->check('thead input[type="checkbox"]')
            ->assertChecked('thead input[type="checkbox"]')
            ->waitForText('1 receipt selected')
            ->assertSee('1 receipt selected')
            ->assertSee('Categorize')
            ->assertSee('Export')
            ->uncheck('thead input[type="checkbox"]')
            ->assertNotChecked('thead input[type="checkbox"]')
            ->waitUntilMissingText('1 receipt selected')
            ->assertDontSee('1 receipt selected');
    });
});
