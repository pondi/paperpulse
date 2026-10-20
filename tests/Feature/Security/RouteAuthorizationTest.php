<?php

use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/** @return Collection<int, RoutingRoute> */
function applicationRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())->filter(function (RoutingRoute $route): bool {
        $action = $route->getAction('uses');

        return str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\')
            || ($action instanceof Closure && str_starts_with((new ReflectionFunction($action))->getFileName(), base_path('routes').DIRECTORY_SEPARATOR));
    });
}

/** @return list<string> */
function intentionallyPublicRoutes(): array
{
    return [
        'GET /', 'POST invitation-request', 'GET ready', 'GET api/health',
        'GET register', 'POST register', 'GET login', 'POST login',
        'GET forgot-password', 'POST forgot-password', 'GET reset-password/{token}', 'POST reset-password',
        'POST api/v1/auth/login', 'POST api/webdav/auth',
        'GET shared/collections/{token}', 'POST shared/collections/{token}/verify',
        'GET shared/collections/{token}/files/{guid}', 'GET shared/collections/{token}/download',
    ];
}

test('every application route requires authentication and verification unless explicitly public or part of account recovery', function (): void {
    $accountRoutes = [
        'GET verify-email', 'GET verify-email/{id}/{hash}', 'POST email/verification-notification',
        'GET confirm-password', 'POST confirm-password', 'PUT password', 'POST logout',
        'POST api/v1/auth/logout', 'GET api/v1/auth/me',
    ];

    foreach (applicationRoutes() as $route) {
        $identity = $route->methods()[0].' '.$route->uri();
        if (in_array($identity, intentionallyPublicRoutes(), true)) {
            continue;
        }

        $middleware = app('router')->gatherRouteMiddleware($route);
        expect(collect($middleware)->contains(fn (string $name): bool => $name === Authenticate::class || str_starts_with($name, Authenticate::class.':')), $identity)->toBeTrue();

        if (! in_array($identity, $accountRoutes, true)) {
            expect($middleware, $identity)->toContain(EnsureEmailIsVerified::class);
        }
    }
});

test('guests cannot reach any protected application route', function (): void {
    foreach (applicationRoutes() as $route) {
        if (in_array($route->methods()[0].' '.$route->uri(), intentionallyPublicRoutes(), true)) {
            continue;
        }

        $uri = '/'.preg_replace('/\{[^}]+\}/', '999999', $route->uri());
        $response = $this->json($route->methods()[0], $uri);
        expect($response->status(), $route->methods()[0].' '.$uri)->toBe(401);
    }
});

test('unverified users cannot use legacy data APIs', function (string $method, string $uri): void {
    $this->actingAs(User::factory()->unverified()->create())->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/user'], ['GET', '/api/batch'], ['POST', '/api/batch'],
    ['GET', '/api/batch/999999/status'], ['POST', '/api/batch/999999/cancel'],
    ['GET', '/api/receipts/999999/shares'], ['GET', '/api/documents/999999/shares'],
]);

test('verified owners retain access to legacy identity and batch listings', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    $this->getJson('/api/batch')->assertOk()->assertJsonPath('batch_jobs.total', 0);
});

test('profile payloads cannot elevate privileges or overwrite authentication fields', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $passwordHash = $user->password;
    $verifiedAt = $user->email_verified_at;
    $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated name', 'email' => $user->email, 'is_admin' => true,
        'password' => 'injected-password', 'email_verified_at' => null, 'id' => 999999,
    ])->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->isAdmin())->toBeFalse();
    expect($user->password)->toBe($passwordHash);
    expect($user->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});
