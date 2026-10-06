<?php

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\User;
use App\Services\Workers\WorkerFileManager;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

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
            ->and($file->fresh()->meta['review']['page_limit'])->toBeNull()
            ->and($file->fresh()->meta['review']['text_limit_bytes'])->toBe(10)
            ->and($file->extractableEntities()->count())->toBe(0);
        Http::assertNothingSent();
    } finally {
        unlink($path);
    }
});

it('blocks over-limit PDFs before provider calls and retains exact page coverage', function (): void {
    config(['ai.providers.gemini.large_pdf_page_limit' => 1]);
    Http::fake();
    $pdf = new Dompdf;
    $pdf->loadHtml('<p style="page-break-after: always">First page</p><p>Second page</p>');
    $pdf->render();
    $path = tempnam(sys_get_temp_dir(), 'page-limit').'.pdf';
    file_put_contents($path, $pdf->output());
    $file = File::factory()->create(['fileExtension' => 'pdf', 'fileType' => 'application/pdf']);
    $this->mock(WorkerFileManager::class)->shouldReceive('processWithCleanup')->once()
        ->andReturnUsing(fn ($s3, $guid, $extension, $callback) => $callback($path));
    $job = new class($file) extends ProcessFileGemini
    {
        public function __construct(private File $file)
        {
            parent::__construct('page-limit-test');
        }

        protected function getMetadata(): ?array
        {
            return ['fileId' => $this->file->id, 'fileGuid' => $this->file->guid, 'fileExtension' => 'pdf', 's3OriginalPath' => 'original.pdf'];
        }

        protected function updateProgress(int $progress): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    try {
        $job->process();
        expect($file->fresh()->meta['processing_coverage']['total_pages'])->toBe(2)
            ->and($file->fresh()->meta['processing_coverage']['processed_pages'])->toBe(0)
            ->and($file->fresh()->meta['review']['page_limit'])->toBe(1);
        Http::assertNothingSent();
    } finally {
        unlink($path);
    }
});

it('discloses only the active provider processing limits before upload', function (string $provider, ?int $pages): void {
    $this->withoutVite();
    config(['ai.file_processing_provider' => $provider, 'ai.providers.gemini.large_pdf_page_limit' => 25]);
    $this->actingAs(User::factory()->create())->get(route('documents.upload'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $pages === null
            ? $page->where('uploadConfig.processingLimits', null)
            : $page->where('uploadConfig.processingLimits.pdfPages', $pages));
})->with([['gemini', 25], ['textract+openai', null]]);
