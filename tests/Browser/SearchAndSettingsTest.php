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

test('saves currency with the application preference action', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/preferences')
            ->waitFor('#currency')
            ->select('#currency', 'USD')
            ->click('@save-application-preferences')->waitForText('Application preferences saved')
            ->assertScript("document.querySelector('[aria-live=polite]').textContent.trim()", 'Application preferences saved');
    });

    expect($user->fresh()->preference('currency'))->toBe('USD');
});

test('reset preferences button exists', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/preferences')->waitFor('#language')
            ->assertPresent('@reset-application-preferences')
            ->assertAttribute('@reset-application-preferences', 'type', 'button');
    });
});

test('resetting application preferences preserves separately saved organization choices', function (): void {
    $user = $this->createUser();
    $user->preferences()->create([
        'currency' => 'EUR',
        'organization_naming_rules' => ['building_root' => 'Properties', 'work_root' => 'Employment', 'work_structure' => 'year', 'role_labels' => []],
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/preferences')->waitFor('#currency')->screenshot('preferences-mobile')
            ->assertSelected('#currency', 'EUR')->select('#currency', 'USD')->waitForText('Unsaved application preferences')
            ->assertSee('No unsaved organization choices');
        $browser->script('window.confirm = () => true');
        $browser->click('@reset-application-preferences')->waitForText('Application preferences reset')
            ->assertSelected('#currency', 'NOK')
            ->assertScript("document.querySelector('[aria-live=polite]').textContent.trim()", 'Application preferences reset')
            ->assertSee('No unsaved organization choices')->assertInputValue('#preferences-organization input', 'Properties')
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    });

    expect($user->fresh()->preference('currency'))->toBe('NOK')
        ->and($user->fresh()->preferences->organization_naming_rules['building_root'])->toBe('Properties');
});

test('profile navigation separates account deletion from routine account changes', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user);
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->visit('/profile')->waitFor('nav[aria-label="Profile sections"]')
                ->assertPresent('section#account-deletion[aria-labelledby="account-deletion-heading"]')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertScript("Array.from(document.querySelectorAll('nav[aria-label^=Profile] a')).every(link => { const rect = link.getBoundingClientRect(); return rect.height >= 44 && rect.right <= innerWidth; })", true);
            if ($width === 320) {
                $browser->screenshot('profile-settings-mobile');
            }
        }

        $browser->script("document.querySelector('a[href=\"#account-deletion\"]').focus()");
        $browser->keys('nav[aria-label="Profile sections"] a[href="#account-deletion"]', WebDriverKeys::ENTER)
            ->assertScript('location.hash', '#account-deletion')
            ->assertScript("document.querySelector('#account-deletion').closest('form') === null", true)
            ->assertSee('Danger zone')->click('section#account-deletion button')->waitFor('[role="dialog"]')
            ->assertSee('Are you sure you want to delete your account?')->assertPresent('[role="dialog"] input[type="password"]')
            ->screenshot('profile-danger-zone-mobile')
            ->click('[role="dialog"] button')->waitUntilMissing('[role="dialog"]')
            ->assertPresent('#name')->assertPresent('#current_password');
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
