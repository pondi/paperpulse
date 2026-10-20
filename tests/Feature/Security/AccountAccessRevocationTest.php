<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

test('password recovery revokes existing API tokens and browser sessions for that account', function (): void {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $user->createToken('compromised')->plainTextToken;
    $other->createToken('unrelated');
    foreach ([$user, $other] as $account) {
        $account->getConnection()->table('sessions')->insert([
            'id' => 'session-'.$account->id, 'user_id' => $account->id,
            'payload' => '', 'last_activity' => now()->timestamp,
        ]);
    }

    $this->post('/reset-password', [
        'token' => Password::createToken($user), 'email' => $user->email,
        'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

    expect($user->tokens()->count())->toBe(0);
    expect($other->tokens()->count())->toBe(1);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('changing a password revokes tokens and other sessions while keeping the current session authenticated', function (): void {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $user->createToken('old-device');
    $oldSessionId = Str::random(40);
    $user->getConnection()->table('sessions')->insert([
        'id' => $oldSessionId, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password', 'password' => 'replacement-password',
        'password_confirmation' => 'replacement-password',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
    $this->assertDatabaseMissing('sessions', ['id' => $oldSessionId]);
    $this->assertAuthenticatedAs($user);
    expect(Hash::check('replacement-password', $user->fresh()->password))->toBeTrue();
});

test('incorrect passwords cannot revoke credentials', function (): void {
    $user = User::factory()->create();
    $user->createToken('valid');
    $this->actingAs($user)->put('/password', [
        'current_password' => 'incorrect', 'password' => 'replacement-password',
        'password_confirmation' => 'replacement-password',
    ])->assertSessionHasErrors('current_password');
    expect($user->tokens()->count())->toBe(1);
});

test('sessions with stale password hashes cannot read private pages', function (): void {
    $this->withoutVite();
    $user = User::factory()->create();
    $oldHash = $user->password;
    $user->update(['password' => Hash::make('replacement-password')]);
    $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
        ->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('password confirmation attempts are rate limited', function (): void {
    $this->actingAs(User::factory()->create());
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/confirm-password', ['password' => 'incorrect'])->assertSessionHasErrors('password');
    }
    $this->post('/confirm-password', ['password' => 'incorrect'])->assertTooManyRequests();
});

test('logout clears encrypted Inertia history', function (): void {
    $this->withoutVite();
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/')
        ->assertSessionHas('inertia.clear_history', true);
    expect(config('inertia.history.encrypt'))->toBeTrue();
});
