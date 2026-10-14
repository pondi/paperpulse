<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\SavedSearch;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Search Page Tests
|--------------------------------------------------------------------------
*/

test('search page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/search')
            ->waitFor('input[aria-label="Search document contents"]')
            ->assertPresent('input[aria-label="Search document contents"]');
    });
});

test('search with query shows results area', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/search')
            ->waitFor('input[aria-label="Search document contents"]')
            ->type('input[aria-label="Search document contents"]', 'test query')
            ->pause(1000) // Wait for debounced search to fire
            ->assertPresent('section[aria-label="Search results"]');
    });
});

test('entity type filters visible', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/search')
            ->waitFor('input[aria-label="Search document contents"]')
            ->pause(500)
            ->assertSee('Date, amount & location')
            ->assertSee('Type')
            ->assertSelectHasOption('#search-type', 'all')
            ->assertSelectHasOption('#search-type', 'receipt')
            ->assertSelectHasOption('#search-type', 'document')
            ->assertSelectHasOption('#search-type', 'invoice')
            ->assertSelectHasOption('#search-type', 'contract')
            ->assertSelectHasOption('#search-type', 'voucher')
            ->assertSelectHasOption('#search-type', 'warranty')
            ->assertSelectHasOption('#search-type', 'return_policy')
            ->assertSelectHasOption('#search-type', 'bank_statement');
    });
});

/*
|--------------------------------------------------------------------------
| Profile Page Tests
|--------------------------------------------------------------------------
*/

test('profile page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/profile')
            ->waitFor('#name')
            ->assertPresent('#name')
            ->assertPresent('#email')
            ->assertSee('Profile Information');
    });
});

test('can update profile name', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/profile')
            ->waitFor('#name')
            ->clear('#name')
            ->type('#name', 'Updated Name')
            ->pause(300);

        // Click the Save button (PrimaryButton has uppercase CSS, use selector)
        $browser->click('form button')
            ->waitForText('Saved.')
            ->assertSee('Saved.');
    });
});

test('password section visible', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/profile')
            ->waitFor('#name')
            ->assertSee('Update Password')
            ->assertPresent('#current_password')
            ->assertPresent('#password')
            ->assertPresent('#password_confirmation');
    });
});

test('delete account section visible', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/profile')
            ->waitFor('#name')
            ->assertSee('Delete Account')
            ->assertSee('Once your account is deleted');
    });
});

/*
|--------------------------------------------------------------------------
| Preferences Page Tests
|--------------------------------------------------------------------------
*/

test('preferences page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/preferences')
            ->waitFor('#language')
            ->assertPresent('#language')
            ->assertPresent('#timezone')
            ->assertPresent('#date_format')
            ->assertPresent('#currency');
    });
});

test('can change currency', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/preferences')
            ->waitFor('#currency')
            ->select('#currency', 'USD')
            ->pause(300);

        // The save button is far below the fold — scroll to it and click
        $browser->script("document.querySelector('button.bg-zinc-900, button.dark\\\\:bg-amber-600').scrollIntoView()");
        $browser->pause(300);

        // Click the PrimaryButton save (outside the form, at the bottom of the page)
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.classList.contains('uppercase') && btn.textContent.trim().toLowerCase() === 'save') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->assertSee('Saved');
    });
});

test('reset preferences button exists', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/preferences')
            ->waitFor('#language');

        // Scroll to the bottom to find the reset button
        $browser->script('window.scrollTo(0, document.body.scrollHeight)');
        $browser->pause(500)
            // SecondaryButton has uppercase CSS, check for the button element
            ->assertPresent('button.uppercase.border-2');
    });
});

/*
|--------------------------------------------------------------------------
| Entity Pages Load Tests
|--------------------------------------------------------------------------
*/

test('invoices page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/invoices')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/invoices');
    });
});

test('contracts page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/contracts')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/contracts');
    });
});

test('vouchers page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/vouchers')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/vouchers');
    });
});

test('bank statements page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/bank-statements')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/bank-statements');
    });
});

test('analytics page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/analytics')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/analytics');
    });
});

test('files page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/files-processing')
            ->pause(1000)
            ->waitFor('main', 10)
            ->assertPathIs('/files-processing');
    });
});

it('finds financial records with grouped filters and supports keyboard preview and collection actions', function (): void {
    $user = $this->createUser();
    $collection = Collection::factory()->for($user)->create(['name' => 'Travel expenses']);
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Northstar']);
    $file = File::factory()->for($user)->create(['fileName' => 'Northstar travel receipt.pdf', 'status' => 'completed']);
    $receipt = Receipt::factory()->for($user)->create(['file_id' => $file->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-02-10', 'total_amount' => 200, 'currency' => 'EUR']);
    ExtractableEntity::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true]);
    Receipt::factory()->for($user)->create(['file_id' => File::factory()->for($user)->create()->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-03-10', 'total_amount' => 50]);

    $this->browse(function (Browser $browser) use ($user, $file, $receipt, $collection): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/search?query=Northstar&type=receipt&amount_min=100&date_to=2026-02-28')
            ->waitForText('Northstar travel receipt.pdf')->assertSee('1 result')->assertSee('No currency conversion')
            ->assertSee('Matches the receipt date')->assertSee('Item vendor (receipts)')
            ->keys('button[aria-label="Preview receipt Northstar (#'.$receipt->id.')"]', WebDriverKeys::ENTER)
            ->waitFor('[role="dialog"]')->waitForText('Document information')
            ->keys('[role="dialog"] button[aria-label="Close preview"]', WebDriverKeys::ESCAPE)
            ->waitUntilMissing('[role="dialog"]')
            ->check('input[aria-label="Select receipt Northstar (#'.$receipt->id.')"]')->press('Add to Collection')
            ->waitFor('#bulk-collection')->select('#bulk-collection', (string) $collection->id)
            ->click('[role="dialog"] button.workspace-primary')->waitUntilMissing('#bulk-collection')
            ->waitForText('Files added to the collection.');
        expect($file->collections()->whereKey($collection->id)->exists())->toBeTrue();
        $browser->select('#search-collection', (string) $collection->id)
            ->waitUsing(10, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'collection_id='))
            ->click('button[aria-controls="search-filters"]')->assertMissing('#search-filters')
            ->assertSee('Collection: Travel expenses')->assertSee('Minimum: 100')
            ->assertSee('To: 2026-02-28')->assertMissing('header input[aria-label="Search your library"]')->screenshot('search-redesign-desktop');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->screenshot('search-redesign-'.$width)->click('button[aria-controls="search-filters"]')
                ->waitFor('#search-filters')->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->click('button[aria-controls="search-filters"]');
        }
        $browser->script('document.documentElement.classList.add("dark")');
        $browser->pause(250)->screenshot('search-redesign-dark');
        $browser->script('document.documentElement.classList.remove("dark")');
        $browser->resize(1440, 1000)->click('button[aria-controls="search-filters"]')->waitFor('#search-amount-max')
            ->type('#search-amount-max', '10')->waitFor('[role="alert"]')
            ->assertSee('The maximum amount must be at least the minimum amount.')
            ->assertInputValue('#search-amount-max', '10')->click('button[aria-label="Remove Maximum: 10"]')
            ->waitUntilMissing('[role="alert"]')->waitForText('1 result');
    });
});

it('restores search pagination and filters from history and saved views', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Archive supplier']);
    foreach (range(1, 22) as $number) {
        Receipt::factory()->for($user)->create(['file_id' => File::factory()->for($user)->create(['fileName' => 'Archive '.$number.'.pdf'])->id, 'merchant_id' => $merchant->id, 'total_amount' => 150]);
    }
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/search?type=receipt&amount_min=100')
            ->waitForText('22 results')->press('Next')->waitForText('Page 2 of 2')->assertQueryStringHas('page', '2')
            ->click('aside a[href$="/library"]')->waitFor('@library-query')->back()->waitFor('#search-type')
            ->assertSelected('#search-type', 'receipt')->assertInputValue('#search-amount-min', '100')->assertSee('Page 2 of 2');
        $browser->script('window.scrollTo(0, 0)');
        $browser->click('@save-view')->waitFor('@saved-view-name')->type('@saved-view-name', 'Receipt archive')->click('@confirm-save-view')
            ->waitForText('Save changes');
        $saved = SavedSearch::where('user_id', $user->id)->firstOrFail();
        expect($saved->filters['amount_min'])->toBe('100')->and($saved->filters['type'])->toBe('receipt');
        $browser->press('Clear filters (keep search)')->waitForText('Start searching')
            ->click('aside a[href$="/saved-views/'.$saved->id.'"]')->waitForText('22 results')
            ->assertSelected('#search-type', 'receipt')->assertInputValue('#search-amount-min', '100')
            ->type('input[aria-label="Search document contents"]', 'No matching record')
            ->waitForText('No results found')->assertSee('Try another phrase');
    });
});
