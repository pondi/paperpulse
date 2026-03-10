<?php

declare(strict_types=1);

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
            ->waitFor('input[type="text"][placeholder*="Search"]')
            ->assertPresent('input[type="text"][placeholder*="Search"]');
    });
});

test('search with query shows results area', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/search')
            ->waitFor('input[type="text"][placeholder*="Search"]')
            ->type('input[type="text"][placeholder*="Search"]', 'test query')
            ->pause(1000) // Wait for debounced search to fire
            ->assertPresent('.flex-1'); // Results area container
    });
});

test('entity type filters visible', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/search')
            ->waitFor('input[type="text"][placeholder*="Search"]')
            ->pause(500)
            ->assertSee('Filters')
            ->assertSee('Type')
            ->assertPresent('input[type="radio"][value="all"]')
            ->assertPresent('input[type="radio"][value="receipt"]')
            ->assertPresent('input[type="radio"][value="document"]')
            ->assertPresent('input[type="radio"][value="invoice"]')
            ->assertPresent('input[type="radio"][value="contract"]')
            ->assertPresent('input[type="radio"][value="voucher"]')
            ->assertPresent('input[type="radio"][value="warranty"]')
            ->assertPresent('input[type="radio"][value="return_policy"]')
            ->assertPresent('input[type="radio"][value="bank_statement"]');
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
        $browser->script("window.scrollTo(0, document.body.scrollHeight)");
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
