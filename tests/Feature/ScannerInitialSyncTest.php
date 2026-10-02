<?php

use App\Jobs\PulseDav\SyncPulseDavFiles;
use App\Models\User;
use App\Services\PulseDavService;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

it('discovers a first scheduled scanner upload without receipts or realtime sync', function (): void {
    $user = User::factory()->create();
    $prefix = 'incoming/'.$user->id.'/';
    config(['services.pulsedav.s3_incoming_prefix' => 'incoming/', 'filesystems.disks.pulsedav.bucket' => 'scanner-test']);
    $s3 = Mockery::mock(S3Client::class);
    Storage::shouldReceive('disk')->with('pulsedav')->andReturn(
        Mockery::mock()->shouldReceive('getClient')->andReturn($s3)->getMock()
    );
    $s3->shouldReceive('listObjectsV2')->twice()->with(['Bucket' => 'scanner-test', 'Prefix' => $prefix])
        ->andReturn(new Result(['Contents' => [['Key' => $prefix.'first.pdf', 'Size' => 12, 'LastModified' => now()]]]));

    expect($user->receipts()->exists())->toBeFalse()
        ->and($user->pulseDavFiles()->exists())->toBeFalse()
        ->and($user->preference('pulsedav_realtime_sync', false))->toBeFalse();
    $job = new SyncPulseDavFiles;
    $service = new PulseDavService;
    $job->handle($service);
    $job->handle($service);

    $this->assertDatabaseHas('pulsedav_files', ['user_id' => $user->id, 's3_path' => $prefix.'first.pdf', 'status' => 'pending']);
    $this->assertDatabaseCount('pulsedav_files', 1);
});

it('continues first-upload discovery after another user fails to sync', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $service = Mockery::mock(PulseDavService::class);
    $service->shouldReceive('syncS3Files')->once()->with(Mockery::on(fn (User $user): bool => $user->is($first)))
        ->andThrow(new RuntimeException('Storage offline'));
    $service->shouldReceive('syncS3Files')->once()->with(Mockery::on(fn (User $user): bool => $user->is($second)))->andReturn(0);

    (new SyncPulseDavFiles)->handle($service);
});
