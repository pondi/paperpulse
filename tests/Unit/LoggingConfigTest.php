<?php

use Monolog\Handler\SyslogUdpHandler;
use Tests\TestCase;

uses(TestCase::class);

it('ignores empty-string logging env values', function () {
    $setEnvVar = static function (string $key, ?string $value): void {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    };

    $previous = [
        'LOG_CHANNEL' => getenv('LOG_CHANNEL'),
        'LOG_STACK' => getenv('LOG_STACK'),
        'LOG_STDERR_FORMATTER' => getenv('LOG_STDERR_FORMATTER'),
        'LOG_PAPERTRAIL_HANDLER' => getenv('LOG_PAPERTRAIL_HANDLER'),
    ];

    try {
        $setEnvVar('LOG_CHANNEL', '');
        $setEnvVar('LOG_STACK', '');
        $setEnvVar('LOG_STDERR_FORMATTER', '');
        $setEnvVar('LOG_PAPERTRAIL_HANDLER', '');

        $config = require base_path('config/logging.php');

        expect($config['default'])->toBe('stack');
        expect($config['channels']['stack']['channels'])->toBe(['single']);
        expect($config['channels']['stderr']['formatter'])->toBeNull();
        expect($config['channels']['papertrail']['handler'])->toBe(SyslogUdpHandler::class);
    } finally {
        foreach ($previous as $key => $value) {
            $setEnvVar($key, $value === false ? null : $value);
        }
    }
});
