<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Models\Document;
use App\Models\File;
use App\Services\DocumentAnalysisService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('completes the real text document pipeline with validated extracted dates', function (string $text, ?string $expected) {
    Queue::fake();
    $file = File::factory()->create(['fileExtension' => 'txt', 'fileName' => 'document.txt']);
    $path = tempnam(sys_get_temp_dir(), 'document_');
    file_put_contents($path, $text);
    $this->mock(WorkerFileManager::class, function ($mock) use ($path): void {
        $mock->shouldReceive('ensureLocalFile')->once()->andReturn($path);
        $mock->shouldReceive('cleanupLocalFile')->once();
    });
    $metadata = ['fileId' => $file->id, 'userId' => $file->user_id, 'fileGuid' => $file->guid, 'fileExtension' => 'txt',
        'jobName' => 'document', 's3OriginalPath' => 'source.txt', 'extractedText' => $text];
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, $metadata);
    $this->mock(DocumentAnalysisService::class)->shouldReceive('analyze')->once()
        ->andReturn(['title' => 'Analyzed document', 'document_type' => 'document']);
    $job = new class($jobId) extends ProcessDocument
    {
        protected function updateProgress(int $progress): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    try {
        $job->process();
        expect(Document::query()->where('file_id', $file->id)->exists())->toBeFalse()
            ->and($file->fresh()->status)->toBe('processing');
        $analysisJob = new class($jobId) extends AnalyzeDocument
        {
            protected function updateProgress(int $progress): void {}

            public function process(): void
            {
                $this->handleJob();
            }
        };
        $analysisJob->process();
        $document = Document::query()->where('file_id', $file->id)->firstOrFail();
        expect($file->fresh()->status)->toBe('completed')
            ->and($document->document_date?->format('Y-m-d'))->toBe($expected);
    } finally {
        unlink($path);
    }
})->with([
    ['Document issued 2024-02-29', '2024-02-29'],
    ['Document issued 2024-02-30', null],
    ['Document issued 03/04/2024', null],
]);
