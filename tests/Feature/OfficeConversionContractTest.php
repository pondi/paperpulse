<?php

use App\Jobs\Files\ConvertOfficeFile;
use App\Models\FileConversion;
use App\Services\Documents\ConversionCapabilities;
use App\Services\Documents\ConversionService;
use App\Services\Documents\LocalOfficeConverter;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake();
    config()->set('processing.conversion.driver', 'local');
});

it('uses a durable owned conversion request with distinct original and archive paths', function (): void {
    $file = createOfficeConversionFile();
    $archive = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $service = app(ConversionService::class);
    $first = $service->queueConversion($file, $file->s3_original_path, $archive);
    $second = $service->queueConversion($file, $file->s3_original_path, $archive);

    expect($second->id)->toBe($first->id)->and(FileConversion::count())->toBe(1);
    expect($first->metadata['driver'])->toBe('local');
    expect($first->input_s3_path)->not->toBe($first->output_s3_path);
    Bus::assertDispatched(ConvertOfficeFile::class, 1);
    expect($service->waitForCompletion($first)['success'])->toBeFalse();
});

it('rejects disabled conversion before recording or dispatching work', function (): void {
    config()->set('processing.conversion.enabled', false);
    $file = createOfficeConversionFile();
    expect(fn () => app(ConversionService::class)->queueConversion($file, $file->s3_original_path, 'archive.pdf'))
        ->toThrow(RuntimeException::class, 'disabled');
    expect(FileConversion::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('rejects unsupported extensions and a foreign original path before dispatch', function (): void {
    $file = createOfficeConversionFile();
    expect(fn () => app(ConversionService::class)->queueConversion($file, 'another-user/source.docx', 'archive.pdf'))
        ->toThrow(RuntimeException::class, 'owned original');
    $file->update(['fileExtension' => 'exe']);
    expect(fn () => app(ConversionService::class)->queueConversion($file, $file->s3_original_path, 'archive.pdf'))
        ->toThrow(RuntimeException::class, 'Unsupported conversion extension');
    expect(FileConversion::count())->toBe(0);
});

it('keeps original bytes and writes only a verified PDF archive', function (): void {
    $file = createOfficeConversionFile();
    $archive = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $conversion = app(ConversionService::class)->queueConversion($file, $file->s3_original_path, $archive);
    $storage = $this->mock(StorageService::class);
    $storage->shouldReceive('getFile')->once()->with($file->s3_original_path)->andReturn(conversionDocxFixture());
    $storage->shouldReceive('storeFile')->once()->withArgs(fn ($bytes, $owner, $guid, $type, $variant, $extension) => str_starts_with($bytes, '%PDF-')
        && $owner === $file->user_id && $guid === $file->guid && $type === 'document' && $variant === 'archive' && $extension === 'pdf')->andReturn($archive);
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()->andReturnUsing(function ($source, $destination, $mime): void {
        expect($mime)->toBe(ConversionCapabilities::OFFICE_MIME_TYPES['docx']);
        file_put_contents($destination, conversionPdfFixture());
    });

    app(ConversionService::class)->convert($conversion);
    expect($conversion->refresh()->status)->toBe('completed');
    expect($file->refresh()->s3_original_path)->not->toBe($file->s3_archive_path);
    expect($file->s3_archive_path)->toBe($archive);
});

it('records retryable driver failures without accepting malformed output or changing originals', function (): void {
    $file = createOfficeConversionFile();
    $archive = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $conversion = app(ConversionService::class)->queueConversion($file, $file->s3_original_path, $archive);
    $this->mock(StorageService::class)->shouldReceive('getFile')->once()->andReturn(conversionDocxFixture());
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()->andReturnUsing(fn ($source, $destination) => file_put_contents($destination, '%PDF-broken'));
    expect(fn () => app(ConversionService::class)->convert($conversion))->toThrow(RuntimeException::class, 'valid nonempty PDF');
    expect($conversion->refresh()->status)->toBe('failed')->and($file->refresh()->s3_archive_path)->toBeNull();
    expect(app(ConversionService::class)->retry($conversion))->toBeTrue();
    expect($conversion->refresh()->retry_count)->toBe(1);
});

it('rejects the retired external driver before recording work', function (): void {
    config()->set('processing.conversion.driver', 'external');
    $file = createOfficeConversionFile();
    expect(fn () => app(ConversionService::class)->queueConversion($file, $file->s3_original_path, 'archive.pdf'))
        ->toThrow(RuntimeException::class, 'Unsupported office conversion driver');
    expect(FileConversion::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('normalizes configuration and generation failures during queued execution', function (): void {
    $file = createOfficeConversionFile();
    $archive = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $service = app(ConversionService::class);
    $conversion = $service->queueConversion($file, $file->s3_original_path, $archive);
    config()->set('processing.conversion.enabled', false);
    expect(fn () => $service->convert($conversion))->toThrow(RuntimeException::class, 'disabled');
    expect($conversion->refresh()->status)->toBe('failed')->and($conversion->error_message)->not->toBeNull();
    config()->set('processing.conversion.enabled', true);
    $file->update(['meta' => ['processing_generation' => 'replacement']]);
    expect(fn () => $service->convert($conversion))->toThrow(RuntimeException::class, 'obsolete');
    expect($conversion->refresh()->status)->toBe('failed')->and($file->refresh()->s3_archive_path)->toBeNull();
});

it('rechecks durable retry state before dispatching a stale second retry', function (): void {
    $file = createOfficeConversionFile();
    $archive = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $service = app(ConversionService::class);
    $conversion = $service->queueConversion($file, $file->s3_original_path, $archive);
    $conversion->update(['metadata' => array_merge($conversion->metadata, ['driver' => 'external'])]);
    $conversion->markAsFailed('Temporary failure');
    $stale = $conversion->fresh();
    Bus::fake();
    expect($service->retry($conversion))->toBeTrue()->and($service->retry($stale))->toBeFalse();
    expect($conversion->refresh()->retry_count)->toBe(1)->and($conversion->metadata['driver'])->toBe('local');
    Bus::assertDispatched(ConvertOfficeFile::class, 1);
});
