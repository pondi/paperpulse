<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Exceptions\FileProcessingFailedException;
use App\Models\File;
use App\Services\AI\TypeClassification\AutomaticTypeResolver;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Exceptions::fake();
});

it('uses a confident classification regardless of the upload type without paying for a resolution pass', function (string $uploadedType, string $detectedType): void {
    $file = File::factory()->create(['file_type' => $uploadedType]);
    $this->mock(GeminiTypeClassifier::class)->shouldNotReceive('resolve');
    $this->mock(TextAnalysisContract::class)->shouldNotReceive('analyze');
    $initial = new ClassificationResult($detectedType, .95, 'Clear source content');
    expect(app(AutomaticTypeResolver::class)->resolve($file, $initial, 'file-uri', [], null, 'run', 'hash'))->toBe($initial);
})->with([['receipt', 'document'], ['document', 'receipt'], ['receipt', 'contract']]);

it('resolves uncertain scans once and reuses the resolution on redelivery', function (): void {
    $file = File::factory()->create(['file_type' => 'receipt']);
    $this->mock(GeminiTypeClassifier::class)->shouldReceive('resolve')->once()->andReturn(new ClassificationResult('document', .92, 'Letter, no purchase or payment'));
    $this->mock(TextAnalysisContract::class)->shouldNotReceive('analyze');
    $resolver = app(AutomaticTypeResolver::class);
    $initial = new ClassificationResult('unknown', .4, 'Ambiguous');
    foreach (range(1, 2) as $attempt) {
        expect($resolver->resolve($file, $initial, 'uri', [], null, 'run', 'hash')->type)->toBe('document');
    }
    expect($file->fresh()->meta['automatic_classification']['generic_fallback'])->toBeFalse();
    Exceptions::assertNothingReported();
});

it('uses generic document extraction for unresolved content and reports the fallback instead of asking for a type', function (): void {
    $file = File::factory()->create();
    $this->mock(GeminiTypeClassifier::class)->shouldReceive('resolve')->once()->andReturn(new ClassificationResult('receipt', .4, 'Ambiguous purchase details'));
    $result = app(AutomaticTypeResolver::class)->resolve($file, new ClassificationResult('unknown', .2, 'Unclear'), 'uri', [], null, 'run', 'hash');
    expect($result->type)->toBe('document')->and($result->confidence)->toBe(.4)
        ->and($file->fresh()->meta['automatic_classification']['generic_fallback'])->toBeTrue();
    Exceptions::assertReported(fn (FileProcessingFailedException $exception): bool => str_contains($exception->getMessage(), 'Automatic classification remained ambiguous'));
});

it('classifies a bounded source excerpt and caches it independently of upload labels', function (): void {
    $file = File::factory()->create(['file_type' => 'document']);
    $this->mock(TextAnalysisContract::class, function ($mock): void {
        $mock->shouldReceive('getProviderName')->andReturn('fake');
        $mock->shouldReceive('analyze')->once()->withArgs(function (string $prompt, array $schema): bool {
            expect(strlen($prompt))->toBeLessThan(7000)->and($schema['properties']['document_type']['enum'])->toBe(['receipt', 'document']);

            return true;
        })->andReturn(['document_type' => 'receipt', 'confidence' => .95, 'reasoning' => 'Completed purchase with item prices and payment']);
    });
    $resolver = app(AutomaticTypeResolver::class);
    $text = str_repeat('Payment receipt ', 2000);
    expect($resolver->fromText($file, $text, 'run')->type)->toBe('receipt')
        ->and($resolver->fromText($file, $text, 'run')->type)->toBe('receipt');
});
