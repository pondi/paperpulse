<?php

use App\Jobs\Documents\ProcessDocument;
use App\Models\Document;
use App\Models\File;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\Queue;

it('completes the real text document pipeline with validated extracted dates', function (string $text, ?string $expected) {
    Queue::fake();
    $file = File::factory()->create(['fileExtension' => 'txt', 'fileName' => 'document.txt']);
    $path = tempnam(sys_get_temp_dir(), 'document_').'.txt';
    file_put_contents($path, $text);
    $this->mock(WorkerFileManager::class, function ($mock) use ($path): void {
        $mock->shouldReceive('ensureLocalFile')->once()->andReturn($path);
        $mock->shouldReceive('cleanupLocalFile')->once();
    });
    $metadata = ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileExtension' => 'txt',
        'jobName' => 'document', 's3OriginalPath' => 'source.txt', 'extractedText' => $text];
    $job = new class('date-document', $metadata) extends ProcessDocument
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
