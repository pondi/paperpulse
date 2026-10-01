<?php

use App\Jobs\Files\ProcessFile;
use App\Models\File;
use App\Services\Documents\ConversionService;
use App\Services\Files\FileProcessingCapabilities;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use App\Services\Workers\WorkerFileManager;

it('skips paid OCR and conversion for native Gemini documents while retaining legacy OCR', function (string $provider, string $extension, bool $expectsOcr) {
    config(['ai.file_processing_provider' => 'textract+openai']);
    $file = File::factory()->create(['fileExtension' => $extension]);
    $this->mock(ConversionService::class, function ($mock) use ($expectsOcr): void {
        if ($expectsOcr) {
            $mock->shouldReceive('requiresConversion')->once()->andReturnFalse();
        } else {
            $mock->shouldNotReceive('requiresConversion');
        }
        $mock->shouldNotReceive('queueConversion');
    });
    $this->mock(StorageService::class, fn ($mock) => $mock->shouldReceive('getFile')->once()->andReturn('source'));
    $this->mock(TextExtractionService::class, function ($mock) use ($expectsOcr): void {
        if ($expectsOcr) {
            $mock->shouldReceive('extract')->once()->andReturn('Reusable OCR checkpoint');
        } else {
            $mock->shouldNotReceive('extract');
        }
    });
    $this->mock(WorkerFileManager::class, function ($mock) use ($expectsOcr): void {
        if ($expectsOcr) {
            $mock->shouldReceive('ensureLocalFile')->once()->andReturn('/tmp/capability-source.pdf');
            $mock->shouldReceive('cleanupLocalFile')->once();
        } else {
            $mock->shouldNotReceive('ensureLocalFile');
        }
    });
    $metadata = ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileType' => 'document', 'fileExtension' => $extension,
        'filePath' => null, 's3OriginalPath' => 'source.'.$extension, 'processingProvider' => $provider];
    $job = new class('capability-test', $metadata) extends ProcessFile
    {
        public function __construct(string $id, public array $metadata)
        {
            parent::__construct($id);
        }

        protected function getMetadata(): ?array
        {
            return $this->metadata;
        }

        protected function storeMetadata(array $metadata): void
        {
            $this->metadata = $metadata;
        }

        protected function updateProgress(int $progress): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    $job->process();
    expect($job->metadata['pipeline_stages']['ocr']['status'])->toBe($expectsOcr ? 'completed' : 'skipped_native')
        ->and($job->metadata['pipeline_stages']['preprocessing']['provider'])->toBe($provider);
    if ($expectsOcr) {
        expect($job->metadata['extractedText'])->toBe('Reusable OCR checkpoint');
    }
    if ($provider === 'ocr-only') {
        expect($file->fresh()->status)->toBe('completed');
    }
})->with([['gemini', 'pdf', false], ['gemini', 'txt', false], ['textract+openai', 'pdf', true], ['ocr-only', 'pdf', true]]);

it('requires OCR only for an explicit fallback and rejects unknown providers before work', function () {
    expect(FileProcessingCapabilities::fromMetadata(['processingProvider' => 'gemini', 'ocrFallback' => true])->requiresOcr())->toBeTrue()
        ->and(fn () => new FileProcessingCapabilities('unknown'))->toThrow(InvalidArgumentException::class);
});
