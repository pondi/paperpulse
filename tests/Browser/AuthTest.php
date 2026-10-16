<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

test('login page loads correctly', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('#email')
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertPresent('form button');
    });
});

test('successful login redirects to dashboard', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('#email')
            ->type('#email', $user->email)
            ->type('#password', 'password')
            ->click('form button')
            ->waitForLocation('/dashboard')
            ->assertPathIs('/dashboard');
    });
});

test('login with wrong password shows error', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('#email')
            ->type('#email', $user->email)
            ->type('#password', 'wrong-password')
            ->click('form button')
            ->waitForText('These credentials do not match')
            ->assertSee('These credentials do not match');
    });
});

test('remember me checkbox exists and is clickable', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('#email')
            ->assertVisible('input[name="remember"]')
            ->assertSee('Remember me')
            ->check('input[name="remember"]')
            ->assertChecked('input[name="remember"]');
    });
});

test('registration page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->waitFor('#name')
            ->assertSee('Name')
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertSee('Confirm Password');
    });
});

test('registration requires invitation warning', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->waitForText('Registration requires an invitation')
            ->assertSee('Registration requires an invitation');
    });
});

test('successful registration with invitation requires email verification', function () {
    $email = 'dusk-register-'.strtolower(Str::random(8)).'@example.com';
    $token = Str::random(64);

    Invitation::create([
        'email' => $email,
        'token' => $token,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->browse(function (Browser $browser) use ($token) {
        $browser->visit('/register?token='.$token)
            ->waitFor('#name')
            ->assertSee('You\'ve been invited')
            ->type('#name', 'Dusk Test User')
            ->type('#password', 'SecurePass123!')
            ->type('#password_confirmation', 'SecurePass123!')
            ->click('form button')
            ->waitForLocation('/verify-email')
            ->assertPathIs('/verify-email')
            ->visit('/dashboard')
            ->waitForLocation('/verify-email')
            ->assertPathIs('/verify-email');
    });

    expect(User::query()->where('email', $email)->firstOrFail()->hasVerifiedEmail())->toBeFalse();
    expect(Invitation::query()->where('token', $token)->firstOrFail()->isUsed())->toBeTrue();
});

test('registration with weak password shows validation error', function () {
    $email = 'dusk-weak-pw-'.strtolower(Str::random(8)).'@example.com';
    $token = Str::random(64);

    Invitation::create([
        'email' => $email,
        'token' => $token,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->browse(function (Browser $browser) use ($token) {
        $browser->visit('/register?token='.$token)
            ->waitFor('#name')
            ->type('#name', 'Dusk Test User')
            ->type('#password', 'short')
            ->type('#password_confirmation', 'short')
            ->click('form button')
            ->waitForText('password field must be at least')
            ->assertSee('password field must be at least');
    });
});

test('forgot password page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/forgot-password')
            ->waitFor('#email')
            ->assertSee('Email')
            ->assertSee('Reset your password')
            ->assertSee('Back to sign in')
            ->assertPresent('form button');
    });
});

test('guest authentication and recovery screens are titled accessible and responsive', function (): void {
    $email = 'dusk-auth-'.strtolower(Str::random(8)).'@example.com';
    $invitation = Invitation::create(['email' => $email, 'status' => 'sent']);

    $this->browse(function (Browser $browser) use ($email, $invitation): void {
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->visit('/login')->waitFor('#email')
                ->assertSeeIn('h1', 'Sign in')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertPresent('label[for="email"]')
                ->assertAttribute('#email', 'autocomplete', 'username')
                ->assertScript("document.querySelector('form button').getBoundingClientRect().height >= 44", true);

            if ($width === 320) {
                $browser->screenshot('auth-login-mobile');
            }

            $browser->visit('/forgot-password')->waitFor('#email')
                ->assertSeeIn('h1', 'Reset your password')
                ->assertSee('Back to sign in')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);

            $browser->visit('/register?token='.$invitation->token)->waitFor('#name')
                ->assertSeeIn('h1', 'Create your account')
                ->assertSee('You\'ve been invited')
                ->assertAttribute('#name', 'autocomplete', 'name')
                ->assertAttribute('#password', 'autocomplete', 'new-password')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);

            $browser->visit('/reset-password/preview?email='.urlencode($email))->waitFor('#password')
                ->assertSeeIn('h1', 'Choose a new password')
                ->assertAttribute('#password_confirmation', 'autocomplete', 'new-password')
                ->assertSee('Back to sign in')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
        }
    });
});

test('login validation errors are announced and connected to the invalid field', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(390, 844)->visit('/login')->waitFor('#email')
            ->type('#email', $user->email)
            ->type('#password', 'incorrect-password')
            ->click('form button')->waitFor('[role="alert"]')
            ->assertAttribute('#email', 'aria-invalid', 'true')
            ->assertAttribute('#email', 'aria-describedby', 'email-error')
            ->assertPresent('#email-error[role="alert"]');
    });
});

test('welcome invitation flow keeps its clear outcome on mobile', function (): void {
    $email = 'dusk-invite-'.strtolower(Str::random(8)).'@example.com';

    $this->browse(function (Browser $browser) use ($email): void {
        $browser->resize(320, 844)->visit('/')->waitForText('Your receipts.')
            ->screenshot('welcome-invitation-mobile')
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->click('main button')->waitFor('[role="dialog"]')
            ->assertSee('Request an Invitation')
            ->screenshot('welcome-invitation-dialog-mobile')
            ->assertAttribute('#name', 'autocomplete', 'name')
            ->assertAttribute('#email', 'autocomplete', 'email')
            ->assertScript("Array.from(document.querySelectorAll('[role=dialog] button')).every(button => button.getBoundingClientRect().height >= 44)", true)
            ->type('#name', 'Dusk Invitation Request')
            ->type('#email', $email)
            ->type('#company', 'PaperPulse Test')
            ->click('[role="dialog"] button[type="submit"]')
            ->waitForText('We\'ve received your invitation request')
            ->assertPresent('[role="status"][aria-live="polite"]');
    });

    expect(Invitation::query()->where('email', $email)->firstOrFail()->status)->toBe('pending');
});

test('logout works', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->script('return Promise.all([caches.open("paperpulse-scanner-logout-test"), caches.open("logout-unrelated-test")]);');
        $browser->click('button[aria-label="Account menu"]')
            ->waitForText('Log out')
            ->press('Log out')
            ->waitForLocation('/')
            ->assertScript('caches.keys().then(keys => !keys.some(name => name.startsWith("paperpulse-scanner-")))', true)
            ->assertScript('caches.keys().then(keys => keys.includes("logout-unrelated-test"))', true)
            ->visit('/dashboard')
            ->waitForLocation('/login')
            ->assertPathIs('/login');
        $browser->script('return caches.delete("logout-unrelated-test");');
    });
});

test('unauthenticated users are redirected to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->waitForLocation('/login')
            ->assertPathIs('/login');
    });
});

test('Inertia progress styles use the document CSP nonce across navigation', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/login')->waitFor('#email');
        $browser->script(<<<'JS'
            window.cspStyleViolations = [];
            document.addEventListener('securitypolicyviolation', (event) => {
                if (event.effectiveDirective.startsWith('style-src')) {
                    window.cspStyleViolations.push(event.effectiveDirective);
                }
            });
            document.dispatchEvent(new CustomEvent('inertia:start', {
                detail: { visit: { showProgress: true } },
            }));
        JS);
        $browser->waitFor('#nprogress .bar')
            ->assertScript('getComputedStyle(document.querySelector("#nprogress .bar")).backgroundColor', 'rgb(75, 85, 99)')
            ->assertScript('getComputedStyle(document.querySelector("#nprogress .bar")).position', 'fixed')
            ->assertScript(<<<'JS'
                Array.from(document.querySelectorAll('style')).some((style) =>
                    style.textContent.includes('#nprogress .bar') &&
                    style.nonce === document.querySelector('meta[name="csp-nonce"]').content &&
                    style.sheet.cssRules.length > 0
                )
            JS);
        $browser->script(<<<'JS'
            document.dispatchEvent(new CustomEvent('inertia:finish', {
                detail: { visit: { completed: true } },
            }));
        JS);
        $browser->waitUntilMissing('#nprogress')
            ->clickLink('Forgot your password?')
            ->waitForLocation('/forgot-password')
            ->assertScript('window.cspStyleViolations.length', 0);
    });
});
