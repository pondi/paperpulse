<?php

use Illuminate\Support\Facades\Storage;

it('passes verified TLS options to every configured S3 client', function (string $disk): void {
    config()->set("filesystems.disks.{$disk}.key", 'test-key');
    config()->set("filesystems.disks.{$disk}.secret", 'test-secret');
    config()->set("filesystems.disks.{$disk}.region", 'us-east-1');
    config()->set("filesystems.disks.{$disk}.bucket", 'test-bucket');

    $client = Storage::disk($disk)->getClient();

    expect(config("filesystems.disks.{$disk}.client.http.verify"))->not->toBeFalse();
    expect($client->getCommand('ListBuckets')['@http']['verify'] ?? true)->toBeTrue();
})->with(['paperpulse', 'pulsedav', 'uplink']);

it('passes a configured CA bundle to the effective S3 client', function (): void {
    $config = config('filesystems.disks.paperpulse');
    $config['key'] = 'test-key';
    $config['secret'] = 'test-secret';
    $config['region'] = 'us-east-1';
    $config['bucket'] = 'test-bucket';
    $config['http']['verify'] = '/etc/ssl/custom-ca.pem';

    expect(Storage::build($config)->getClient()->getCommand('ListBuckets')['@http']['verify'])->toBe('/etc/ssl/custom-ca.pem');
});
