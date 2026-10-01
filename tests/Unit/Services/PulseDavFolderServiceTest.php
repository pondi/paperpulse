<?php

use App\Models\User;
use App\Services\PulseDav\PulseDavFolderService;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('lists named folders and files without returning the requested folder marker', function (string $folderPath) {
    config([
        'services.pulsedav.s3_incoming_prefix' => 'scans/incoming/',
        'filesystems.disks.pulsedav.bucket' => 'scanner-test',
    ]);
    $user = User::factory()->make(['id' => 42]);
    $prefix = 'scans/incoming/42/'.($folderPath === '' ? '' : $folderPath.'/');
    $uploadedAt = new DateTimeImmutable('2026-10-01T12:00:00Z');
    $s3Client = Mockery::mock(S3Client::class);
    $s3Client->shouldReceive('listObjectsV2')->once()->with([
        'Bucket' => 'scanner-test',
        'Prefix' => $prefix,
        'Delimiter' => '/',
    ])->andReturn(new Result([
        'CommonPrefixes' => [
            ['Prefix' => $prefix.'contracts/'],
            ['Prefix' => $prefix.'invoices/'],
        ],
        'Contents' => [
            ['Key' => $prefix, 'Size' => 0, 'LastModified' => $uploadedAt],
            ['Key' => $prefix.'receipt.pdf', 'Size' => 123, 'LastModified' => $uploadedAt],
        ],
    ]));
    Storage::shouldReceive('disk')->once()->with('pulsedav')
        ->andReturn(Mockery::mock()->shouldReceive('getClient')->once()->andReturn($s3Client)->getMock());

    $items = (new PulseDavFolderService)->getFolderContents($user, $folderPath);

    expect($items)->toBe([
        [
            'name' => 'contracts',
            's3_path' => $prefix.'contracts/',
            'path' => $prefix.'contracts/',
            'is_folder' => true,
            'size' => 0,
            'uploaded_at' => null,
        ],
        [
            'name' => 'invoices',
            's3_path' => $prefix.'invoices/',
            'path' => $prefix.'invoices/',
            'is_folder' => true,
            'size' => 0,
            'uploaded_at' => null,
        ],
        [
            'name' => 'receipt.pdf',
            's3_path' => $prefix.'receipt.pdf',
            'path' => $prefix.'receipt.pdf',
            'is_folder' => false,
            'size' => 123,
            'uploaded_at' => $uploadedAt,
        ],
    ]);
})->with([
    'user root' => '',
    'nested folder' => '2026/october',
]);
