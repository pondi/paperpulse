<?php

declare(strict_types=1);

use App\Services\S3StorageService;
use Illuminate\Support\Facades\Storage;

// --- Put & Get ---

it('stores and retrieves file content', function () {
    Storage::fake('s3');

    S3StorageService::put('s3', 'test/file.txt', 'hello world');

    expect(S3StorageService::get('s3', 'test/file.txt'))->toBe('hello world');
});

// --- Exists ---

it('checks if file exists', function () {
    Storage::fake('s3');

    expect(S3StorageService::exists('s3', 'missing.txt'))->toBeFalse();

    S3StorageService::put('s3', 'present.txt', 'data');

    expect(S3StorageService::exists('s3', 'present.txt'))->toBeTrue();
});

// --- Size ---

it('returns file size', function () {
    Storage::fake('s3');

    S3StorageService::put('s3', 'sized.txt', 'twelve chars');

    expect(S3StorageService::size('s3', 'sized.txt'))->toBe(12);
});

// --- Delete ---

it('deletes a file', function () {
    Storage::fake('s3');

    S3StorageService::put('s3', 'deleteme.txt', 'bye');

    expect(S3StorageService::exists('s3', 'deleteme.txt'))->toBeTrue();

    S3StorageService::delete('s3', 'deleteme.txt');

    expect(S3StorageService::exists('s3', 'deleteme.txt'))->toBeFalse();
});

// --- Edge Cases ---

it('throws when reading non-existent file', function () {
    Storage::fake('s3');

    S3StorageService::get('s3', 'nonexistent.txt');
})->throws(TypeError::class);
