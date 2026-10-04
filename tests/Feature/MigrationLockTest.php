<?php

use App\Services\MigrationLock;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Cache;

it('holds the migration mutex across cache clearing and elapsed legacy TTL', function (): void {
    $first = new MigrationLock(app(DatabaseManager::class));
    $second = new MigrationLock(app(DatabaseManager::class));
    try {
        expect($first->acquire())->toBeTrue();
        expect($first->acquire())->toBeTrue();
        Cache::flush();
        $this->travel(301)->seconds();
        expect($second->acquire())->toBeFalse();
        $second->release();
        expect($second->acquire())->toBeFalse();
        $first->release();
        expect($second->acquire())->toBeTrue();
        $first->release();
        expect($first->acquire())->toBeFalse();
        $second->release();
        expect($first->acquire())->toBeTrue();
    } finally {
        $first->release();
        $second->release();
    }
});

it('fails the command safely without releasing another migration owner', function (): void {
    $this->mock(MigrationLock::class, function ($mock): void {
        $mock->shouldReceive('acquire')->once()->andReturn(false);
        $mock->shouldNotReceive('release');
    });

    $this->artisan('migrate:safe')->assertFailed();
});
