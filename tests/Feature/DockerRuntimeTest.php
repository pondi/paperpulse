<?php

use App\Console\Commands\ForgePreflight;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('runs supported PHP and isolated PostgreSQL tests', function (): void {
    expect(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)->toBe('8.5');
    expect(config('database.connections'))->toHaveKeys(['pgsql', 'pgsql_locks']);
    expect(array_keys(config('database.connections')))->toHaveCount(2);
    $connection = app('db')->connection();
    expect($connection->getDriverName())->toBe('pgsql');
    expect($connection->getDatabaseName())->toBe('paperpulse_test');
    expect($connection->selectOne('SELECT current_user AS username')->username)->toBe('paperpulse_test');
    foreach (['GEMINI_API_KEY', 'OPENAI_API_KEY', 'TEXTRACT_KEY', 'TEXTRACT_SECRET'] as $credential) {
        expect(getenv($credential))->toBe('isolated-test');
    }
});

it('rejects unsupported databases in local environments before connecting', function (): void {
    $original = config('database.default');
    try {
        app()->instance('env', 'local');
        config()->set('database.default', 'unsupported');
        expect(fn () => (new AppServiceProvider(app()))->boot())
            ->toThrow(RuntimeException::class, 'requires PostgreSQL');
    } finally {
        config()->set('database.default', $original);
    }
});

it('removes framework default connections while preserving PostgreSQL settings', function (): void {
    $connections = config('database.connections');
    try {
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
        config()->set('database.connections.mysql', ['driver' => 'mysql']);
        (new AppServiceProvider(app()))->register();

        expect(config('database.connections'))->toBe($connections);
        expect(fn () => app('db')->connection('sqlite'))
            ->toThrow(InvalidArgumentException::class, 'not configured');
    } finally {
        config()->set('database.connections', $connections);
    }
});

it('prevents the test role from connecting to development data', function (): void {
    if (! getenv('PAPERPULSE_CONTAINER_RUNTIME')) {
        $this->markTestSkipped('Requires the container PostgreSQL roles.');
    }
    $configuration = app('db')->connection()->getConfig();
    $configuration['database'] = 'paperpulse';
    expect(fn () => (new PostgresConnector)->connect($configuration))
        ->toThrow(PDOException::class, 'permission denied for database');
});

it('reaches the bundled search and private S3 services', function (): void {
    if (! getenv('PAPERPULSE_CONTAINER_RUNTIME')) {
        $this->markTestSkipped('Requires the container search and storage services.');
    }
    Http::get(config('scout.meilisearch.host').'/health')->throw();
    $path = 'runtime-tests/'.Str::uuid().'.txt';
    $disk = Storage::disk('paperpulse');
    try {
        expect($disk->put($path, 'Private runtime fixture'))->toBeTrue();
        expect($disk->get($path))->toBe('Private runtime fixture');
        $url = $disk->temporaryUrl($path, now()->addMinute());
        expect($url)->toContain('X-Amz-Signature');
        expect(Http::get($url)->throw()->body())->toBe('Private runtime fixture');
        expect(Http::get(strtok($url, '?'))->status())->toBe(403);
    } finally {
        $disk->delete($path);
    }
});

it('includes the required PHP extensions in the application image', function (): void {
    if (! getenv('PAPERPULSE_CONTAINER_RUNTIME')) {
        $this->markTestSkipped('Requires the container PHP extensions.');
    }
    foreach ([...ForgePreflight::EXTENSIONS, 'pcntl', 'posix'] as $extension) {
        expect(extension_loaded($extension))->toBeTrue('Missing ext-'.$extension);
    }
});
