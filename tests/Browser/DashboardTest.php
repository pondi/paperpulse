<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;

test('dashboard page loads after login', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitForText('PaperPulse')
            ->assertSee('PaperPulse');
    });
});

test('dashboard shows stat cards', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('.grid.grid-cols-1')
            ->assertPresent('.grid.grid-cols-1 .border-l-4.border-amber-600')
            ->assertPresent('.grid.grid-cols-1 .border-l-4.border-orange-600')
            ->assertPresent('.grid.grid-cols-1 .border-l-4.border-red-600')
            ->assertPresent('.grid.grid-cols-1 .border-l-4.border-amber-500');
    });
});

test('sidebar navigation links are visible', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->assertPresent('a[href$="/dashboard"]')
            ->assertPresent('a[href$="/search"]')
            ->assertPresent('a[href$="/receipts"]')
            ->assertPresent('a[href$="/documents"]')
            ->assertPresent('a[href$="/tags"]')
            ->assertPresent('a[href$="/collections"]')
            ->assertPresent('a[href$="/documents/upload"]')
            ->assertPresent('a[href$="/analytics"]')
            ->assertPresent('a[href$="/files-processing"]');
    });
});

test('clicking receipts link navigates to receipts page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/receipts"]')
            ->waitForLocation('/receipts')
            ->assertPathIs('/receipts');
    });
});

test('clicking documents link navigates to documents page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/documents"]')
            ->waitForLocation('/documents')
            ->assertPathIs('/documents');
    });
});

test('clicking upload link navigates to upload page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/documents/upload"]')
            ->waitForLocation('/documents/upload')
            ->assertPathIs('/documents/upload');
    });
});

test('clicking tags link navigates to tags page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/tags"]')
            ->waitForLocation('/tags')
            ->assertPathIs('/tags');
    });
});

test('clicking collections link navigates to collections page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/collections"]')
            ->waitForLocation('/collections')
            ->assertPathIs('/collections');
    });
});

test('clicking vouchers child link navigates to vouchers page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/vouchers"]')
            ->waitForLocation('/vouchers')
            ->assertPathIs('/vouchers');
    });
});

test('clicking invoices child link navigates to invoices page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/invoices"]')
            ->waitForLocation('/invoices')
            ->assertPathIs('/invoices');
    });
});

test('clicking contracts child link navigates to contracts page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/contracts"]')
            ->waitForLocation('/contracts')
            ->assertPathIs('/contracts');
    });
});

test('clicking bank statements child link navigates to bank statements page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/bank-statements"]')
            ->waitForLocation('/bank-statements')
            ->assertPathIs('/bank-statements');
    });
});

test('clicking categories child link navigates to categories page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('div.hidden.xl\\:fixed a[href$="/documents/categories"]')
            ->waitForLocation('/documents/categories')
            ->assertPathIs('/documents/categories');
    });
});

test('search bar is visible in header', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('input[type="search"]')
            ->assertPresent('input[type="search"][placeholder*="Search"]');
    });
});

test('user menu shows profile and preferences links', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitForText($user->name)
            ->click('div.xl\\:pl-72 button[class*="flex"][class*="items-center"]')
            ->pause(500)
            ->waitFor('a[href$="/profile"]')
            ->assertPresent('a[href$="/profile"]')
            ->assertPresent('a[href$="/preferences"]');
    });
});
