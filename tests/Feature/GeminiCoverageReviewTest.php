<?php

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\Http;

it('retains over-limit native text for review before any provider upload or extraction', function () {
    config(['ai.providers.gemini.api_key' => 'test', 'ai.providers.gemini.text_max_bytes' => 10]);
    Http::fake();
    $path = tempnam(sys_get_temp_dir(), 'coverage_').'.txt';
    file_put_contents($path, 'A document exceeding the configured native processing limit');
    $file = File::factory()->create(['fileExtension' => 'txt', 'fileType' => 'text/plain']);
    $this->mock(WorkerFileManager::class, function ($mock) use ($path): void {
        $mock->shouldReceive('processWithCleanup')->once()->andReturnUsing(fn ($s3, $guid, $extension, $callback) => $callback($path));
    });
    $metadata = ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileExtension' => 'txt', 's3OriginalPath' => 'source.txt'];
    $job = new class('coverage-test', $metadata) extends ProcessFileGemini
    {
        public function __construct(string $id, private array $metadata)
        {
            parent::__construct($id);
        }

        protected function getMetadata(): ?array
        {
            return $this->metadata;
        }

        protected function updateProgress(int $progress): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    try {
        $job->process();
        expect($file->fresh()->status)->toBe('needs_review')
            ->and($file->fresh()->meta['processing_coverage']['complete'])->toBeFalse()
            ->and($file->extractableEntities()->count())->toBe(0);
        Http::assertNothingSent();
    } finally {
        unlink($path);
    }
});
