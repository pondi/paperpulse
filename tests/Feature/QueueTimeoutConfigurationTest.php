<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Files\ProcessFile;

it('reserves queue jobs longer than every worker and job timeout', function (): void {
    $workerTimeout = config('queue.worker_timeout');

    expect((new AnalyzeDocument('test'))->timeout)->toBeLessThan($workerTimeout);
    expect((new ProcessFile('test'))->timeout)->toBeLessThan($workerTimeout);
    expect(config('processing.conversion.timeout'))->toBeLessThan((new ProcessFile('test'))->timeout);

    foreach (['database', 'redis', 'beanstalkd'] as $connection) {
        expect(config("queue.connections.{$connection}.retry_after"))->toBeGreaterThan($workerTimeout);
    }

    $supervisor = file_get_contents(base_path('deploy/docker/supervisord.conf'));
    expect($supervisor)->toContain('--timeout='.$workerTimeout, '--queue='.implode(',', config('queue.worker_queues')));

    preg_match('/\[program:worker\].*?stopwaitsecs=(\d+)/s', $supervisor, $matches);
    expect((int) $matches[1])->toBeGreaterThan($workerTimeout);
});
