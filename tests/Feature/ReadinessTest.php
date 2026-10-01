<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('health.required', ['database', 'migrations']);
});

it('has one liveness route independent of unavailable required services', function (): void {
    config()->set('health.required', ['unknown-service']);

    expect(collect(Route::getRoutes())->filter(fn ($route) => $route->uri() === 'up'))->toHaveCount(1);
    $this->get('/up')->assertOk();
    $this->getJson('/ready')->assertServiceUnavailable()->assertJsonPath('status', 'down');
});

it('reports ready when all required migrations are applied', function (): void {
    $this->getJson('/ready')->assertOk()->assertJsonPath('components.migrations.status', 'ok');
});

it('fails readiness for a migration on disk that is missing from the database', function (): void {
    $repository = app(Migrator::class)->getRepository();
    $repository->delete((object) ['migration' => $repository->getRan()[0]]);

    $this->getJson('/ready')->assertServiceUnavailable()->assertJsonPath('components.migrations.status', 'down');
});

it('ignores historical applied migrations that are no longer on disk', function (): void {
    app(Migrator::class)->getRepository()->log('historical_removed_migration', 1);

    $this->getJson('/ready')->assertOk()->assertJsonPath('components.migrations.status', 'ok');
});

it('reports a required service failure without exposing credentials', function (): void {
    config()->set('health.required', ['redis']);
    Illuminate\Support\Facades\Redis::shouldReceive('connection')->with('health')
        ->andThrow(new RuntimeException('secret connection credentials'));

    $this->getJson('/ready')->assertServiceUnavailable()
        ->assertJsonPath('components.redis.status', 'down')
        ->assertDontSee('secret connection credentials');
});

it('bounds PostgreSQL connection and query durations in the effective DSN', function (): void {
    $connector = new class extends App\Services\HealthPostgresConnector
    {
        public function exposeDsn(array $configuration): string
        {
            return $this->getDsn($configuration);
        }
    };
    $dsn = $connector->exposeDsn(['host' => '127.0.0.1', 'database' => 'health', 'port' => 5432]);

    expect($dsn)->toContain('connect_timeout=2', 'statement_timeout=2000', 'lock_timeout=1000');
});
