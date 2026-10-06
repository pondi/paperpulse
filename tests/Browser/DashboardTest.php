<?php

declare(strict_types=1);

use App\Models\Receipt;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->browse(fn (Browser $browser) => $browser->resize(1440, 1000));
});

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
    Receipt::factory()->create(['user_id' => $user->id]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->click('details summary')->waitFor('.grid.grid-cols-1')
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
            ->assertPresent('aside a[href$="/library"]')
            ->assertPresent('aside a[href$="/saved-views"]')
            ->assertPresent('a[href$="/collections"]')
            ->assertPresent('@add-document')
            ->assertPresent('a[href$="/analytics"]')
            ->assertPresent('a[href$="/files-processing"]');
    });
});

test('library receipts tab filters the document workspace', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'receipt')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=receipt'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'receipt');
    });
});

test('library documents tab filters the document workspace', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'document')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=document'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'document');
    });
});

test('clicking upload link navigates to upload page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('@add-document')
            ->waitFor('a[href$="/documents/upload"]')
            ->click('a[href$="/documents/upload"]')
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
            ->click('aside a[href$="/collections"]')
            ->waitForLocation('/collections')
            ->click('nav[aria-label="Workspace navigation"] a[href$="/tags"]')
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
            ->click('aside a[href$="/collections"]')
            ->waitForLocation('/collections')
            ->assertPathIs('/collections');
    });
});

test('library document type selector includes vouchers', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'voucher')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=voucher'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'voucher');
    });
});

test('library invoices tab filters the document workspace', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'invoice')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=invoice'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'invoice');
    });
});

test('library contracts tab filters the document workspace', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'contract')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=contract'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'contract');
    });
});

test('library bank statements tab filters the document workspace', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/library"]')
            ->waitFor('@library-query')
            ->select('select[aria-label="Document type"]', 'bank_statement')
            ->waitUsing(5, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'type=bank_statement'))
            ->assertPathIs('/library')->assertQueryStringHas('type', 'bank_statement');
    });
});

test('clicking categories child link navigates to categories page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user)
            ->assertPathIs('/dashboard')
            ->waitFor('nav')
            ->click('aside a[href$="/collections"]')
            ->waitForLocation('/collections')
            ->click('nav[aria-label="Workspace navigation"] a[href$="/documents/categories"]')
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
            ->click('button[aria-label="Account menu"]')
            ->pause(500)
            ->waitFor('a[href$="/profile"]')
            ->assertPresent('a[href$="/profile"]')
            ->assertPresent('a[href$="/preferences"]');
    });
});
