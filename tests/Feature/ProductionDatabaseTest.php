<?php

use App\Providers\AppServiceProvider;

it('rejects unsupported production databases before handling requests', function (): void {
    config()->set('database.default', 'sqlite');
    app()->instance('env', 'production');

    expect(fn () => (new AppServiceProvider(app()))->boot())
        ->toThrow(RuntimeException::class, 'production requires PostgreSQL');
});

it('rejects an unsupported Forge database', function (): void {
    config()->set('database.default', 'sqlite');
    $this->artisan('forge:preflight')->expectsOutputToContain('requires PostgreSQL')->assertFailed();
});

it('accepts a PostgreSQL production configuration without connecting during boot', function (): void {
    $original = config('database.default');
    try {
        config()->set('database.default', 'pgsql');
        app()->instance('env', 'production');
        (new AppServiceProvider(app()))->boot();
        expect(app()->make('db')->connection()->getDriverName())->toBe('pgsql');
    } finally {
        config()->set('database.default', $original);
    }
});
