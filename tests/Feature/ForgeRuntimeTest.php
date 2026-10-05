<?php

use App\Console\Commands\RuntimeCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('reports pending and reserved database jobs across every supported queue without Redis', function (): void {
    config()->set('queue.default', 'database');
    foreach (config('queue.worker_queues') as $queue) {
        $id = Queue::connection('database')->push(function (): void {}, queue: $queue);
        DB::table('jobs')->where('id', $id)->update(['reserved_at' => now()->timestamp]);
        Queue::connection('database')->push(function (): void {}, queue: $queue);
    }
    expect(Artisan::call('queue:health', ['--format' => 'json']))->toBe(0);
    $output = json_decode(Artisan::output(), true);
    foreach (config('queue.worker_queues') as $queue) {
        expect($output['queues'][$queue])->toBe(['pending' => 1, 'ready' => 1, 'delayed' => 0, 'processing' => 1]);
    }
});

it('distinguishes delayed jobs from ready jobs until their scheduled time', function (int $delay): void {
    $this->freezeTime();
    config()->set('queue.default', 'database');
    $queue = Queue::connection('database');
    $reservedId = $queue->push(function (): void {}, queue: 'documents');
    DB::table('jobs')->where('id', $reservedId)->update(['reserved_at' => now()->timestamp]);
    $queue->push(function (): void {}, queue: 'documents');
    $queue->later(now()->addSeconds($delay), function (): void {}, queue: 'documents');

    expect(Artisan::call('queue:health', ['--format' => 'json']))->toBe(0);
    $output = json_decode(Artisan::output(), true);
    expect($output['queues']['documents'])->toBe(['pending' => 2, 'ready' => 1, 'delayed' => 1, 'processing' => 1]);
    expect(Artisan::call('queue:health'))->toBe(0);
    expect(Artisan::output())->toContain('Ready', 'Delayed');

    $this->travel($delay)->seconds();

    expect(Artisan::call('queue:health', ['--format' => 'json']))->toBe(0);
    $output = json_decode(Artisan::output(), true);
    expect($output['queues']['documents'])->toBe(['pending' => 2, 'ready' => 2, 'delayed' => 0, 'processing' => 1]);
})->with([1, 300, 86400]);

it('reports failed database jobs without relying on worker dashboards', function (): void {
    config()->set('queue.default', 'database');
    foreach (range(1, 6) as $index) {
        DB::table('failed_jobs')->insert(['uuid' => Str::uuid(), 'connection' => 'database', 'queue' => 'conversions', 'payload' => '{}', 'exception' => 'Fixture failure', 'failed_at' => now()]);
    }
    $this->artisan('queue:health')->expectsOutputToContain('Failed jobs: 6')->assertFailed();
});

it('rejects Redis queue cache and Reverb scaling in the optional runtime check', function (string $key, mixed $value, string $message): void {
    $original = config('database.default');
    config()->set('database.default', 'pgsql');
    config(['queue.default' => 'database', 'cache.default' => 'database', 'session.driver' => 'database']);
    config(['queue.failed.database' => 'pgsql', 'queue.batching.database' => 'pgsql']);
    config()->set($key, $value);
    try {
        $this->artisan('runtime:check')->expectsOutputToContain($message)->assertFailed();
    } finally {
        config()->set('database.default', $original);
    }
})->with([
    ['queue.default', 'redis', 'requires database'],
    ['cache.default', 'redis', 'requires database'],
    ['reverb.servers.reverb.scaling.enabled', true, 'Disable Reverb scaling'],
]);

it('registers a generic runtime diagnostic without the Forge deployment command', function (): void {
    expect(Artisan::all())->toHaveKey('runtime:check')->not->toHaveKey('forge:preflight');
});

it('checks configured external search readiness with PostgreSQL cache and queues', function (int $searchStatus): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.debug' => false,
        'database.default' => 'pgsql',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'session.driver' => 'database',
        'broadcasting.default' => 'log',
        'reverb.servers.reverb.scaling.enabled' => false,
        'ai.file_processing_provider' => 'gemini',
        'ai.providers.gemini.api_key' => 'isolated-test',
        'filesystems.disks.paperpulse.driver' => 's3',
        'filesystems.disks.paperpulse.bucket' => 'private-storage',
        'filesystems.disks.pulsedav.driver' => 's3',
        'filesystems.disks.pulsedav.bucket' => 'private-incoming',
        'processing.conversion.driver' => 'local',
        'scout.meilisearch.host' => 'https://search.example.test',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://search.example.test/health' => Http::response(['status' => 'available'], $searchStatus)]);
    Process::fake(fn () => Process::result(output: "PHP Version => 8.5.0\n".implode("\n", RuntimeCheck::EXTENSIONS)));

    if ($searchStatus === 200) {
        $this->artisan('runtime:check', ['--after-migrations' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Application runtime verified.')->assertSuccessful();
    } else {
        $this->artisan('runtime:check', ['--after-migrations' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Database migrations, queue or Meilisearch readiness failed.')->assertFailed();
    }

    Http::assertSent(fn ($request): bool => $request->url() === 'https://search.example.test/health');
})->with([200, 503]);
