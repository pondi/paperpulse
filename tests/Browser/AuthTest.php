<?php

use App\Models\Invitation;
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

test('successful registration with invitation redirects to dashboard', function () {
    $email = 'dusk-register-' . strtolower(Str::random(8)) . '@example.com';
    $token = Str::random(64);

    Invitation::create([
        'email' => $email,
        'token' => $token,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->browse(function (Browser $browser) use ($email, $token) {
        $browser->visit('/register?token=' . $token)
            ->waitFor('#name')
            ->assertSee('You\'ve been invited')
            ->type('#name', 'Dusk Test User')
            ->type('#password', 'SecurePass123!')
            ->type('#password_confirmation', 'SecurePass123!')
            ->click('form button')
            ->waitForLocation('/dashboard')
            ->assertPathIs('/dashboard');
    });
});

test('registration with weak password shows validation error', function () {
    $email = 'dusk-weak-pw-' . strtolower(Str::random(8)) . '@example.com';
    $token = Str::random(64);

    Invitation::create([
        'email' => $email,
        'token' => $token,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->browse(function (Browser $browser) use ($email, $token) {
        $browser->visit('/register?token=' . $token)
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
            ->assertSee('Forgot your password?')
            ->assertPresent('form button');
    });
});

test('logout works', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->waitForText($user->name)
            ->pause(500);

        // Use fetch with XSRF cookie (meta csrf-token is stale after Inertia login)
        $browser->script("
            const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);
            fetch('/logout', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': xsrf, 'Accept': 'text/html' },
                credentials: 'same-origin'
            }).then(() => { window.location.href = '/login'; });
        ");

        $browser->waitForLocation('/login')
            ->assertPathIs('/login');
    });
});

test('unauthenticated users are redirected to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->waitForLocation('/login')
            ->assertPathIs('/login');
    });
});
