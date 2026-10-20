<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('log viewer routes reject guests and nonadministrators in every environment', function (string $environment, string $actor): void {
    app()->instance('env', $environment);
    if ($actor !== 'guest') {
        $this->actingAs(User::factory()->create(['is_admin' => $actor === 'unverified admin', 'email_verified_at' => $actor === 'unverified admin' ? null : now()]));
    }

    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'log-viewer.'));
    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', 'invalid', $route->uri());
        $this->json($route->methods()[0], $uri)->assertForbidden();
    }
})->with(['local', 'staging', 'production'])->with(['guest', 'regular', 'unverified admin']);

test('verified administrators can use the log viewer', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson('/log-viewer/api/hosts')->assertOk();
});

test('browser login helpers are inaccessible outside the testing environment', function (string $environment): void {
    $user = User::factory()->create();
    app()->instance('env', $environment);
    $this->get('/_dusk/login/'.$user->id)->assertNotFound();
    $this->assertGuest();
    $this->get('/_dusk/user')->assertNotFound();
    $this->get('/_dusk/logout')->assertNotFound();
})->with(['local', 'staging', 'production']);

test('browser login helpers remain usable for isolated browser tests', function (): void {
    $user = User::factory()->create();
    $this->get('/_dusk/login/'.$user->id)->assertSuccessful();
    $this->assertAuthenticatedAs($user);
});

test('state changing browser endpoints require CSRF protection', function (string $uri): void {
    app()->instance('env', 'production');
    $this->postJson($uri, [], ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://attacker.example'])
        ->assertStatus(419);
})->with(['/login', '/register', '/forgot-password', '/reset-password', '/invitation-request']);

test('private broadcast channels deny guests and other users', function (): void {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
        'driver' => 'reverb', 'app_id' => 'security-test', 'key' => 'security-test', 'secret' => 'security-test',
        'options' => ['host' => 'localhost', 'port' => 80, 'scheme' => 'http', 'useTLS' => false],
    ]]);
    require base_path('routes/channels.php');
    $owner = User::factory()->create();
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$owner->id];
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->actingAs($owner)->postJson('/broadcasting/auth', $payload)->assertOk();
});
