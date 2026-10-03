<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('runs database cache locks and queue workers on PostgreSQL', function (): void {
    if (app('db')->connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Run with the isolated PostgreSQL test database.');
    }
    $key = 'postgres-runtime-'.Str::uuid();
    $cache = Cache::store('database');
    $cache->put($key, 'ready', 60);
    expect($cache->get($key))->toBe('ready');
    $lock = $cache->lock($key.'-lock', 60);
    expect($lock->get())->toBeTrue();
    expect($cache->lock($key.'-lock', 60)->get())->toBeFalse();
    $lock->release();
    expect($cache->lock($key.'-lock', 60)->get())->toBeTrue();
    Queue::connection('database')->push(function () use ($key): void {
        Cache::store('database')->put($key, 'processed', 60);
    }, queue: 'runtime-test');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'runtime-test', '--once' => true, '--no-interaction' => true])->assertSuccessful();
    expect($cache->pull($key))->toBe('processed');
});
