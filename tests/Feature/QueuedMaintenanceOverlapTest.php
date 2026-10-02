<?php

use App\Jobs\Maintenance\CleanupRetainedFiles;
use App\Jobs\PulseDav\SyncPulseDavFiles;
use App\Jobs\PulseDav\SyncPulseDavFilesRealtime;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDavService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config()->set('cache.default', 'database');
});

it('prevents standard and realtime sync from executing simultaneously with database locks', function (): void {
    $first = new SyncPulseDavFiles;
    $second = new SyncPulseDavFilesRealtime;
    $executions = 0;
    $first->middleware()[0]->handle($first, function () use ($second, &$executions): void {
        $executions++;
        $second->middleware()[0]->handle($second, function () use (&$executions): void {
            $executions++;
        });
    });
    expect($executions)->toBe(1)->and($first->connection)->toBe('database')->and($second->connection)->toBe('database');
    $second->middleware()[0]->handle($second, function () use (&$executions): void {
        $executions++;
    });
    expect($executions)->toBe(2);
});

it('uses a shared retention execution lock across distinct scheduled job identities', function (): void {
    $first = new CleanupRetainedFiles;
    $second = new CleanupRetainedFiles;
    $executed = false;
    $first->middleware()[0]->handle($first, function () use ($second, &$executed): void {
        $second->middleware()[0]->handle($second, function () use (&$executed): void {
            $executed = true;
        });
    });
    expect($executed)->toBeFalse()->and($first->jobID)->not->toBe($second->jobID)->and($first->connection)->toBe('database');
});

it('expires abandoned locks after the worker timeout and releases only its own lock', function (): void {
    $job = new SyncPulseDavFiles;
    $middleware = $job->middleware()[0];
    expect($middleware->expiresAfter)->toBeGreaterThan($job->timeout);
    $key = $middleware->getLockKey($job);
    $abandoned = Cache::lock($key, 1);
    expect($abandoned->get())->toBeTrue();
    $this->travel(2)->seconds();
    $successor = Cache::lock($key, 1200);
    expect($successor->get())->toBeTrue()->and($abandoned->release())->toBeFalse();
    $middleware->handle($job, fn () => throw new RuntimeException('Should be locked'));
    expect(Cache::lock($key, 1)->get())->toBeFalse();
    $successor->release();
});

it('loads scheduled sync users in bounded chunks', function (): void {
    $users = User::factory()->count(101)->create();
    foreach ($users as $user) {
        PulseDavFile::create(['user_id' => $user->id, 'filename' => 'source.pdf', 's3_path' => 'incoming/'.$user->id.'/source.pdf', 'uploaded_at' => now()]);
    }
    $sizes = [];
    User::retrieved(function (User $user) use (&$sizes): void {
        $sizes[] = $user->id;
    });
    $service = $this->mock(PulseDavService::class);
    $service->shouldReceive('syncS3Files')->times(101)->andReturn(0);
    $queries = [];
    $this->userConnection = (new User)->getConnection();
    $this->userConnection->listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'from "users"')) {
            $queries[] = $query->sql;
        }
    });
    (new SyncPulseDavFiles)->handle($service);
    expect($sizes)->toHaveCount(101)->and($queries)->toHaveCount(2);
    foreach ($queries as $sql) {
        expect($sql)->toContain('limit 100');
    }
});
