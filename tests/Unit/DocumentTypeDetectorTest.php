<?php

use App\Exceptions\GeminiApiException;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\TypeClassification\ClassificationSchema;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use Tests\TestCase;

uses(TestCase::class);

it('classifies documents using filename extension and MIME hints', function (string $filename, string $extension, string $mime, string $type): void {
    $provider = Mockery::mock(GeminiProvider::class);
    $provider->shouldReceive('analyzeFileByUri')->once()
        ->with('https://gemini.test/fixture', ClassificationSchema::get(),
            Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Filename: '.$filename) && str_contains($prompt, 'Extension: '.$extension)),
            [], $mime)
        ->andReturn(['data' => ['document_type' => $type, 'confidence' => 0.99, 'reasoning' => 'Fixture classification']]);

    $result = (new GeminiTypeClassifier($provider))->classify('https://gemini.test/fixture', [
        'filename' => $filename, 'extension' => $extension, 'mime_type' => $mime,
    ]);

    expect($result->type)->toBe($type)
        ->and($result->confidence)->toBe(0.99)
        ->and($result->isValid())->toBeTrue();
})->with([
    'invoice filename' => ['invoice.pdf', 'pdf', 'application/pdf', 'invoice'],
    'contract filename' => ['contract.pdf', 'pdf', 'application/pdf', 'contract'],
    'bank statement filename' => ['bank-statement.pdf', 'pdf', 'application/pdf', 'bank_statement'],
    'text document' => ['notes.txt', 'txt', 'text/plain', 'document'],
    'receipt image' => ['receipt.png', 'png', 'image/png', 'receipt'],
]);

it('propagates classifier provider failures', function (): void {
    $provider = Mockery::mock(GeminiProvider::class);
    $provider->shouldReceive('analyzeFileByUri')->once()->andThrow(new GeminiApiException('Classification failed'));

    (new GeminiTypeClassifier($provider))->classify('https://gemini.test/fixture');
})->throws(GeminiApiException::class, 'Classification failed');
