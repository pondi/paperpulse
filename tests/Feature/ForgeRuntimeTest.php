<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
        expect($output['queues'][$queue])->toBe(['pending' => 1, 'processing' => 1]);
    }
});

it('reports failed database jobs without relying on worker dashboards', function (): void {
    config()->set('queue.default', 'database');
    foreach (range(1, 6) as $index) {
        DB::table('failed_jobs')->insert(['uuid' => Str::uuid(), 'connection' => 'database', 'queue' => 'conversions', 'payload' => '{}', 'exception' => 'Fixture failure', 'failed_at' => now()]);
    }
    $this->artisan('queue:health')->expectsOutputToContain('Failed jobs: 6')->assertFailed();
});

it('rejects Redis queue cache and Reverb scaling in Forge preflight', function (string $key, mixed $value, string $message): void {
    $original = config('database.default');
    config()->set('database.default', 'pgsql');
    config(['queue.default' => 'database', 'cache.default' => 'database', 'session.driver' => 'database']);
    config(['queue.failed.database' => 'pgsql', 'queue.batching.database' => 'pgsql']);
    config()->set($key, $value);
    try {
        $this->artisan('forge:preflight')->expectsOutputToContain($message)->assertFailed();
    } finally {
        config()->set('database.default', $original);
    }
})->with([
    ['queue.default', 'redis', 'requires database'],
    ['cache.default', 'redis', 'requires database'],
    ['reverb.servers.reverb.scaling.enabled', true, 'Disable Reverb scaling'],
]);
