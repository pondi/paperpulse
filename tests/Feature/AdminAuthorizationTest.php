<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Admin-Protected Routes
|--------------------------------------------------------------------------
|
| Routes using the `admin` middleware (routes/web/admin.php):
|   - jobs.index          GET    /jobs
|   - jobs.status         GET    /jobs/status
|   - jobs.show           GET    /jobs/{jobId}
|   - jobs.restart        POST   /jobs/{jobId}/restart
|   - jobs.restart-multiple POST /jobs/restart-multiple
|
| Routes with inline admin check (abort_unless):
|   - analytics.processing GET   /analytics/processing
|
*/

// --- Helper to create admin and non-admin users ---

function createAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function createRegularUser(): User
{
    return User::factory()->create(['is_admin' => false]);
}

// --- Jobs Index (GET /jobs) ---

it('allows admin users to access jobs index', function () {
    $admin = createAdmin();

    $this->actingAs($admin)
        ->get(route('jobs.index'))
        ->assertOk();
});

it('prevents non-admin users from accessing jobs index', function () {
    $user = createRegularUser();

    $this->actingAs($user)
        ->get(route('jobs.index'))
        ->assertForbidden();
});

it('redirects guests from jobs index to login', function () {
    $this->get(route('jobs.index'))
        ->assertRedirect(route('login'));
});

// --- Jobs Status (GET /jobs/status) ---

it('allows admin users to access jobs status', function () {
    $admin = createAdmin();

    $this->actingAs($admin)
        ->getJson(route('jobs.status'))
        ->assertOk();
});

it('prevents non-admin users from accessing jobs status', function () {
    $user = createRegularUser();

    $this->actingAs($user)
        ->getJson(route('jobs.status'))
        ->assertForbidden();
});

it('redirects guests from jobs status to login', function () {
    $this->get(route('jobs.status'))
        ->assertRedirect(route('login'));
});

// --- Jobs Show (GET /jobs/{jobId}) ---

it('allows admin users to access job show', function () {
    $admin = createAdmin();
    $jobId = (string) Str::uuid();

    $this->actingAs($admin)
        ->getJson(route('jobs.show', $jobId))
        ->assertJsonPath('success', false)
        ->assertNotFound();
});

it('prevents non-admin users from accessing job show', function () {
    $user = createRegularUser();
    $jobId = (string) Str::uuid();

    $this->actingAs($user)
        ->getJson(route('jobs.show', $jobId))
        ->assertForbidden();
});

it('redirects guests from job show to login', function () {
    $jobId = (string) Str::uuid();

    $this->get(route('jobs.show', $jobId))
        ->assertRedirect(route('login'));
});

// --- Jobs Restart (POST /jobs/{jobId}/restart) ---

it('prevents non-admin users from restarting jobs', function () {
    $user = createRegularUser();
    $jobId = (string) Str::uuid();

    $this->actingAs($user)
        ->postJson(route('jobs.restart', $jobId))
        ->assertForbidden();
});

it('allows admin users to call restart endpoint', function () {
    $admin = createAdmin();
    $jobId = (string) Str::uuid();

    // The job won't exist, so we expect a 404 (not 403)
    $response = $this->actingAs($admin)
        ->postJson(route('jobs.restart', $jobId));

    // Admin passes auth — gets 404 because job doesn't exist
    $response->assertNotFound();
});

it('redirects guests from job restart to login', function () {
    $jobId = (string) Str::uuid();

    $this->post(route('jobs.restart', $jobId))
        ->assertRedirect(route('login'));
});

// --- Jobs Restart Multiple (POST /jobs/restart-multiple) ---

it('prevents non-admin users from restarting multiple jobs', function () {
    $user = createRegularUser();

    $this->actingAs($user)
        ->postJson(route('jobs.restart-multiple'), [
            'job_ids' => [(string) Str::uuid()],
        ])
        ->assertForbidden();
});

it('allows admin users to call restart-multiple endpoint', function () {
    $admin = createAdmin();
    $jobId = (string) Str::uuid();

    // Admin passes auth — jobs won't exist but we're testing authorization
    $this->actingAs($admin)
        ->postJson(route('jobs.restart-multiple'), [
            'job_ids' => [$jobId],
        ])
        ->assertOk();
});

it('redirects guests from restart-multiple to login', function () {
    $this->post(route('jobs.restart-multiple'))
        ->assertRedirect(route('login'));
});

// --- Analytics Processing (GET /analytics/processing) ---

it('prevents non-admin users from accessing processing analytics', function () {
    $user = createRegularUser();

    $this->actingAs($user)
        ->get(route('analytics.processing'))
        ->assertForbidden();
});

it('allows admin users to access processing analytics', function () {
    $admin = createAdmin();

    $this->actingAs($admin)
        ->get(route('analytics.processing'))
        ->assertOk();
});

it('redirects guests from processing analytics to login', function () {
    $this->get(route('analytics.processing'))
        ->assertRedirect(route('login'));
});

// --- Bulk authorization check across all admin routes ---

it('returns 403 for non-admin on all admin-protected routes', function (string $method, string $routeName, array $params, array $body) {
    $user = createRegularUser();

    $response = $this->actingAs($user);

    $url = route($routeName, $params);

    match ($method) {
        'GET' => $response->get($url)->assertForbidden(),
        'GET_JSON' => $response->getJson($url)->assertForbidden(),
        'POST_JSON' => $response->postJson($url, $body)->assertForbidden(),
    };
})->with([
    'jobs.index' => ['GET', 'jobs.index', [], []],
    'jobs.status' => ['GET_JSON', 'jobs.status', [], []],
    'jobs.show' => ['GET_JSON', 'jobs.show', ['jobId' => '00000000-0000-0000-0000-000000000001'], []],
    'jobs.restart' => ['POST_JSON', 'jobs.restart', ['jobId' => '00000000-0000-0000-0000-000000000001'], []],
    'jobs.restart-multiple' => ['POST_JSON', 'jobs.restart-multiple', [], ['job_ids' => ['00000000-0000-0000-0000-000000000001']]],
    'analytics.processing' => ['GET', 'analytics.processing', [], []],
]);

it('redirects unauthenticated users to login on all admin routes', function (string $method, string $routeName, array $params) {
    $url = route($routeName, $params);

    match ($method) {
        'GET' => $this->get($url)->assertRedirect(route('login')),
        'POST' => $this->post($url)->assertRedirect(route('login')),
    };
})->with([
    'jobs.index' => ['GET', 'jobs.index', []],
    'jobs.status' => ['GET', 'jobs.status', []],
    'jobs.show' => ['GET', 'jobs.show', ['jobId' => '00000000-0000-0000-0000-000000000001']],
    'jobs.restart' => ['POST', 'jobs.restart', ['jobId' => '00000000-0000-0000-0000-000000000001']],
    'jobs.restart-multiple' => ['POST', 'jobs.restart-multiple', []],
    'analytics.processing' => ['GET', 'analytics.processing', []],
]);
