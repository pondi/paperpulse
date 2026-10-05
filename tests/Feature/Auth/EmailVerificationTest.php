<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => $this->withoutVite());

test('email verification screen can be rendered', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('unverified existing accounts can resend and recover web access', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    $this->post(route('verification.send'))->assertRedirect()->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->artisan('users:send-verification', ['--user' => $user->id])->assertSuccessful();
    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
});

test('verification emails can be resent using PostgreSQL cache without Redis', function (): void {
    config(['cache.default' => 'database']);
    Redis::shouldReceive('connection')->never();
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('verification email resends remain rate limited using PostgreSQL cache', function (): void {
    config(['cache.default' => 'database']);
    Redis::shouldReceive('connection')->never();
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');
    }

    $this->post(route('verification.send'))->assertTooManyRequests();

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});

test('unverified accounts cannot get API tokens or access protected API resources', function () {
    $user = User::factory()->unverified()->create();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
    expect($user->tokens()->count())->toBe(0);
    $token = $user->createToken('existing')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/files')->assertForbidden();
});

test('unsigned and expired verification links are rejected', function () {
    $user = User::factory()->unverified()->create();
    $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
    $this->actingAs($user)->get(route('verification.verify', $parameters))->assertForbidden();
    $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters))->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('PulseDAV follows the same verified account policy', function () {
    config(['services.pulsedav.auth_enabled' => true]);
    $user = User::factory()->unverified()->create();
    $payload = ['username' => $user->email, 'password' => 'password'];
    $this->postJson('/api/webdav/auth', $payload)->assertForbidden();
    $user->markEmailAsVerified();
    $this->postJson('/api/webdav/auth', $payload)->assertOk();
});
