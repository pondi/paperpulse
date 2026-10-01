<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Files\ProcessFile;

it('reserves queue jobs longer than every worker and job timeout', function (): void {
    $supervisorTimeout = config('horizon.defaults.supervisor-1.timeout');

    expect((new AnalyzeDocument('test'))->timeout)->toBeLessThan($supervisorTimeout);
    expect((new ProcessFile('test'))->timeout)->toBeLessThan($supervisorTimeout);
    expect(config('processing.conversion.timeout'))->toBeLessThan((new ProcessFile('test'))->timeout);

    foreach (['database', 'redis', 'beanstalkd'] as $connection) {
        expect(config("queue.connections.{$connection}.retry_after"))->toBeGreaterThan($supervisorTimeout);
    }

    preg_match('/stopwaitsecs=(\d+)/', file_get_contents(base_path('deploy/worker/supervisord.ini')), $matches);
    expect((int) $matches[1])->toBeGreaterThan($supervisorTimeout);
});
