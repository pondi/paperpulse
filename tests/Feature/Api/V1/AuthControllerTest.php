<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Login ---

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => ['user', 'token'],
        ]);
});

it('rejects login with invalid password', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects login with non-existent email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'anything',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('validates login requires email and password', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('validates login email format', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'secret123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

// --- Logout ---

it('logs out an authenticated user and revokes token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('status', 'success');

    // Token should be revoked in the database
    expect($user->tokens()->count())->toBe(0);
});

it('rejects logout without authentication', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

// --- Me ---

it('returns current user info', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.email', $user->email);
});

it('rejects me endpoint without authentication', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});
