<?php

use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$application = require __DIR__.'/../../bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

foreach (['paperpulse', 'pulsedav'] as $diskName) {
    $client = Storage::disk($diskName)->getClient();
    $bucket = config('filesystems.disks.'.$diskName.'.bucket');

    try {
        $client->headBucket(['Bucket' => $bucket]);
    } catch (S3Exception $exception) {
        if ($exception->getStatusCode() !== 404) {
            throw $exception;
        }

        $client->createBucket(['Bucket' => $bucket]);
    }
}
