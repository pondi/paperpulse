<?php

use App\Providers\AppServiceProvider;

it('rejects unsupported production databases before handling requests', function (): void {
    $original = config('database.default');
    try {
        config()->set('database.default', 'unsupported');
        app()->instance('env', 'production');
        expect(fn () => (new AppServiceProvider(app()))->boot())
            ->toThrow(RuntimeException::class, 'requires PostgreSQL');
    } finally {
        config()->set('database.default', $original);
    }
});

it('rejects an unsupported database in the optional runtime check', function (): void {
    $original = config('database.default');
    try {
        config()->set('database.default', 'unsupported');
        $this->artisan('runtime:check')->expectsOutputToContain('requires PostgreSQL')->assertFailed();
    } finally {
        config()->set('database.default', $original);
    }
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
