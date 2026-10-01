<?php

use App\Services\OCR\OCRServiceFactory;
use App\Services\OCR\Providers\TextractProvider;

beforeEach(fn () => OCRServiceFactory::clearCache());
afterEach(fn () => OCRServiceFactory::clearCache());

it('advertises only implemented OCR providers and rejects unavailable configuration early', function () {
    expect(OCRServiceFactory::getAvailableProviders())->toBe(['textract'])
        ->and(OCRServiceFactory::isProviderAvailable('tesseract'))->toBeFalse();
    config(['ai.ocr.provider' => 'tesseract']);
    expect(fn () => OCRServiceFactory::createForFile('source.pdf'))->toThrow(InvalidArgumentException::class, 'Unsupported OCR provider: tesseract');
});

it('selects a capable configured provider without hiding provider failures or unsupported files', function () {
    $provider = $this->mock(TextractProvider::class, function ($mock): void {
        $mock->shouldReceive('canHandle')->with('good.pdf')->andReturnTrue();
        $mock->shouldReceive('canHandle')->with('unsupported.docx')->andReturnFalse();
        $mock->shouldReceive('canHandle')->with('failed.pdf')->andThrow(new RuntimeException('Provider transient failure'));
    });
    expect(OCRServiceFactory::createForFile('good.pdf', ['textract']))->toBe($provider)
        ->and(fn () => OCRServiceFactory::createForFile('unsupported.docx', ['textract']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => OCRServiceFactory::createForFile('failed.pdf', ['textract']))->toThrow(RuntimeException::class, 'Provider transient failure');
});
