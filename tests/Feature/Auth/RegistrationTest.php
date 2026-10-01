<?php

use App\Models\Invitation;

beforeEach(fn () => $this->withoutVite());

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $invitation = Invitation::create([
        'email' => 'test@example.com',
        'status' => 'sent',
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation_token' => $invitation->token,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});


test('invitation registration links reject invalid expired and used tokens', function (string $state) {
    $token = 'unknown';
    if ($state !== 'invalid') {
        $invitation = Invitation::create([
            'email' => 'invite@example.com',
            'token' => \Illuminate\Support\Str::random(64),
            'status' => 'sent',
            'expires_at' => $state === 'expired' ? now()->subMinute() : now()->addDay(),
            'used_at' => $state === 'used' ? now() : null,
        ]);
        $token = $invitation->token;
    }

    $this->get(route('register', ['token' => $token]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('invitation');
})->with(['invalid', 'expired', 'used']);

test('valid invitation links disclose the invited email and token', function () {
    $invitation = Invitation::create(['email' => 'invite@example.com', 'status' => 'sent']);

    $this->get(route('register', ['token' => $invitation->token]))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Auth/Register')
            ->where('invitation.email', $invitation->email)
            ->where('invitation.token', $invitation->token));
});
