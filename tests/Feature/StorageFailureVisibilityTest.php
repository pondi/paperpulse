<?php

use App\Services\File\FileStorageService;
use App\Services\StorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('surfaces configured S3 initialization failures without selecting local storage', function (): void {
    Storage::shouldReceive('disk')->with('pulsedav')->once()->andThrow(new RuntimeException('Invalid bucket configuration'));
    Storage::shouldReceive('disk')->with('local')->never();

    expect(fn () => (new StorageService)->storeFile('source', 1, 'guid', 'document', 'original', 'pdf'))
        ->toThrow(RuntimeException::class, 'Invalid bucket configuration');
});

it('supports local storage only when explicitly configured', function (): void {
    config(['filesystems.incoming_disk' => 'local', 'filesystems.permanent_disk' => 'local']);
    Storage::fake('local');
    $storage = new StorageService;
    $path = $storage->storeFile('source', 1, 'guid', 'document', 'original', 'pdf');

    Storage::disk('local')->assertExists($path);
    expect($storage->isS3Storage())->toBeFalse();
});

it('never returns a working path after a false local write', function (): void {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andReturn(false);
    $disk->shouldReceive('path')->with('uploads/guid')->once()->andReturn(sys_get_temp_dir());
    $disk->shouldNotReceive('path')->with('uploads/guid/source.png');
    Storage::shouldReceive('disk')->with('local')->twice()->andReturn($disk);

    expect(fn () => (new FileStorageService(new StorageService))->storeWorkingContent('data', 'guid', 'png'))
        ->toThrow(Exception::class, 'Failed to write the working file');
});

it('preserves an incoming object when its permanent write fails', function (): void {
    $incoming = Mockery::mock(Filesystem::class);
    $incoming->shouldReceive('exists')->with('incoming/1/source.pdf')->once()->andReturn(true);
    $incoming->shouldReceive('get')->once()->andReturn('source');
    $incoming->shouldNotReceive('delete');
    $permanent = Mockery::mock(Filesystem::class);
    $permanent->shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('pulsedav')->once()->andReturn($incoming);
    Storage::shouldReceive('disk')->with('paperpulse')->once()->andReturn($permanent);

    expect(fn () => (new StorageService)->moveToStorage('incoming/1/source.pdf', 1, 'guid', 'document', 'pdf'))
        ->toThrow(Exception::class, 'Failed to move file to storage bucket');
});

it('reports a failed incoming deletion instead of acknowledging a completed move', function (): void {
    $incoming = Mockery::mock(Filesystem::class);
    $incoming->shouldReceive('exists')->once()->andReturn(true);
    $incoming->shouldReceive('get')->once()->andReturn('source');
    $incoming->shouldReceive('delete')->once()->andReturn(false);
    $permanent = Mockery::mock(Filesystem::class);
    $permanent->shouldReceive('put')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('pulsedav')->once()->andReturn($incoming);
    Storage::shouldReceive('disk')->with('paperpulse')->once()->andReturn($permanent);

    expect(fn () => (new StorageService)->moveToStorage('incoming/1/source.pdf', 1, 'guid', 'document', 'pdf'))
        ->toThrow(Exception::class, 'incoming deletion failed');
});
