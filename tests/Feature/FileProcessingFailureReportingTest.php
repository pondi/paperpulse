<?php

use App\Exceptions\AIResponseException;
use App\Exceptions\FileProcessingFailedException;
use App\Jobs\BaseJob;
use App\Models\File;
use App\Services\AI\PromptTemplateService;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\FileProcessingFailureReporter;
use App\Services\Files\FileReprocessingService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Receipts\Analysis\ReceiptProcessingPolicy;
use App\Services\StorageService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;

beforeEach(function (): void {
    Context::flush();
    config(['broadcasting.default' => 'log']);
    $this->reports = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $exception): void {
        [$record] = app(Core::class)->sensor->exception($exception, null);
        $this->reports[] = ['exception' => $exception, 'context' => Context::all(), 'record' => $record];
    });
});

it('reports storage verification failures including PHP errors without changing prior results', function (string $failure): void {
    $file = File::factory()->create(['status' => 'failed', 's3_original_path' => 'original.pdf']);
    $exception = $failure === 'error' ? new Error('Storage unavailable') : new RuntimeException('Storage unavailable');
    $storage = $this->mock(StorageService::class);
    if ($failure === 'missing') {
        $storage->shouldReceive('existsInStorage')->once()->andReturnFalse();
    } else {
        $storage->shouldReceive('existsInStorage')->once()->andThrow($exception);
    }
    $this->mock(FileJobChainDispatcher::class)->shouldNotReceive('dispatch');
    $result = app(FileReprocessingService::class)->reprocessFile($file);

    expect($result['success'])->toBeFalse()->and($file->fresh()->status)->toBe('failed')
        ->and($this->reports)->toHaveCount(1);
    $report = $this->reports[0];
    expect($report['record']->handled)->toBeFalse()
        ->and($report['context']['processing']['file_id'])->toBe($file->id)
        ->and($report['context']['processing']['file_guid'])->toBe($file->guid)
        ->and($report['context']['processing_failure']['stage'])->toBe('reprocessing-handoff');
    if ($failure !== 'missing') {
        expect($report['exception'])->toBe($exception);
    }
})->with(['missing', 'exception', 'error']);

it('continues bulk retries after a PHP error and reports the affected file', function (): void {
    $files = File::factory()->count(2)->create(['status' => 'failed', 's3_original_path' => 'original.pdf']);
    $this->mock(StorageService::class)->shouldReceive('existsInStorage')->twice()->andReturnTrue();
    $dispatcher = $this->mock(FileJobChainDispatcher::class);
    $exception = new Error('Queue handoff failed');
    $dispatcher->shouldReceive('dispatch')->once()->andThrow($exception);
    $dispatcher->shouldReceive('dispatch')->once();

    $this->artisan('files:reprocess', ['--no-interaction' => true])
        ->expectsOutputToContain('Queued: 1; failed to queue: 1; skipped: 0.')->assertFailed();

    expect($files[0]->fresh()->status)->toBe('failed')->and($files[1]->fresh()->status)->toBe('pending')
        ->and($this->reports)->toHaveCount(1)->and($this->reports[0]['exception'])->toBe($exception)
        ->and($this->reports[0]['context']['processing']['file_id'])->toBe($files[0]->id);
});

it('reports review transitions once per run and groups repeated occurrences by reason', function (string $reason): void {
    $file = File::factory()->create(['status' => 'processing']);
    $review = ['reason' => $reason, 'page_limit' => 25];
    $coverage = ['total_pages' => 30, 'processed_pages' => 0, 'complete' => false];
    $file->update(['status' => 'needs_review', 'meta' => ['review' => $review, 'processing_coverage' => $coverage]]);
    $file->update(['note' => 'Reviewed later']);
    expect($this->reports)->toHaveCount(1);

    $file->update(['status' => 'pending']);
    $file->update(['status' => 'needs_review']);
    expect($this->reports)->toHaveCount(2);
    foreach ($this->reports as $report) {
        expect($report['exception'])->toBeInstanceOf(FileProcessingFailedException::class)
            ->and($report['record']->handled)->toBeFalse()
            ->and($report['context']['processing_failure']['review'])->toBe($review)
            ->and($report['context']['processing_failure']['coverage'])->toBe($coverage)
            ->and($report['context']['processing_failure']['soft'])->toBeTrue();
    }
    expect($this->reports[0]['exception']->getCode())->toBe($this->reports[1]['exception']->getCode())
        ->and(Context::has('processing_failure'))->toBeFalse();
})->with(['processing_limit', 'receipt_totals', 'uncertain_classification']);

it('reports receipt reconciliation failures with their totals', function (): void {
    $file = File::factory()->create(['status' => 'processing']);
    $totals = ['needs_review' => true, 'source_total' => 100, 'calculated_total' => 90];
    ReceiptProcessingPolicy::applyReview($file, $totals);
    expect($this->reports)->toHaveCount(1)
        ->and($this->reports[0]['context']['processing_failure']['review']['reconciliation'])->toBe($totals);
});

it('reports a soft preview failure as an issue even when its worker job completes', function (): void {
    $file = File::factory()->create(['status' => 'completed', 'fileExtension' => 'png']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id, 'processingProvider' => 'gemini', 'metadata' => ['reprocessing' => true]]);
    SoftPreviewFailureReportingJob::dispatch($jobId)->onConnection('database')->onQueue('files');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0, '--no-interaction' => true])->assertSuccessful();

    expect($this->reports)->toHaveCount(1);
    $report = $this->reports[0];
    expect($report['record']->handled)->toBeFalse()
        ->and($report['exception']->getMessage())->toContain('Image file not found')
        ->and($report['context']['processing_failure']['stage'])->toBe('preview')
        ->and($report['context']['processing']['job_id'])->toBe($jobId)
        ->and($report['context']['processing']['reprocessing'])->toBeTrue()
        ->and($file->fresh()->status)->toBe('completed')
        ->and($file->fresh()->image_generation_error)->not->toBeNull();
    $this->assertDatabaseHas('job_history', ['uuid' => $jobId, 'status' => 'completed']);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('does not report intentionally unsupported preview formats or successful processing', function (): void {
    $file = File::factory()->create(['status' => 'processing', 'fileExtension' => 'txt']);
    expect(app(FilePreviewManager::class)->generatePreviewForFile($file, '/source.txt'))->toBeFalse();
    $file->update(['status' => 'completed']);
    expect($this->reports)->toBeEmpty();
});

it('reports AI fallback errors with their original trace and validation details', function (): void {
    Context::add('processing', ['file_id' => 42, 'job_id' => 'retry-job', 'provider' => 'openai']);
    $exception = new AIResponseException('Invalid schema in template', context: ['field' => 'tags', 'errors' => ['Expected a list'], 'api_key' => 'private-key', 'raw_response' => 'private-source']);
    $this->mock(PromptTemplateService::class)->shouldReceive('getPrompt')->once()->andThrow($exception);
    expect((new OpenAIProvider)->generateSummary('Document content'))->toBe('Summary generation failed');
    expect($this->reports)->toHaveCount(1);
    $report = $this->reports[0];
    expect($report['exception'])->toBe($exception)->and($report['record']->handled)->toBeFalse()
        ->and($report['record']->line)->toBe($exception->getLine())
        ->and($report['context']['processing']['file_id'])->toBe(42)
        ->and($report['context']['processing_failure']['errors'])->toBe(['Expected a list'])
        ->and(json_encode($report['context']))->not->toContain('private-key', 'private-source');
    app(FileProcessingFailureReporter::class)->report($exception, 'openai:generateSummary');
    expect($this->reports)->toHaveCount(1)->and(Context::has('processing_failure'))->toBeFalse();
});

class SoftPreviewFailureReportingJob extends BaseJob
{
    protected function handleJob(): void
    {
        $file = File::withoutGlobalScope('user')->findOrFail($this->getMetadata()['fileId']);
        app(FilePreviewManager::class)->generatePreviewForFile($file, '/missing-preview.png');
    }
}
