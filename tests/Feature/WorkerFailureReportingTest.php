<?php

use App\Exceptions\AIResponseException;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\JobHistory;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;

beforeEach(function (): void {
    config(['broadcasting.default' => 'log']);
    $this->reports = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $exception): void {
        [$record] = app(Core::class)->sensor->exception($exception, null);
        $this->reports[] = ['exception' => $exception, 'context' => Context::all(), 'record' => $record];
    });
});

it('reports terminal extraction failures once with the original exception and processing details', function (): void {
    $file = File::factory()->create(['status' => 'processing']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, [
        'fileId' => $file->id, 'fileExtension' => 'pdf', 's3OriginalPath' => 'source.pdf', 'processingProvider' => 'gemini',
    ]);
    $exception = new AIResponseException('Supplemental policy validation failed: invalid return deadline', context: [
        'field' => 'return_policies',
        'errors' => ['The return deadline field must match the format Y-m-d.'],
        'dates' => ['return_deadline' => '30 days'],
        'api_key' => 'secret', 'raw_response' => 'private receipt',
    ]);
    $this->mock(WorkerFileManager::class)->shouldReceive('processWithCleanup')->once()
        ->andReturnUsing(fn () => ProcessingUsageBudget::run($file->user_id, $jobId, 'extraction', fn () => throw $exception));
    ProcessFileGemini::dispatch($jobId)->onConnection('database')->onQueue('files');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0, '--no-interaction' => true])->assertSuccessful();

    expect($this->reports)->toHaveCount(1);
    $report = $this->reports[0];
    expect($report['exception'])->toBe($exception)
        ->and($report['record']->handled)->toBeFalse()
        ->and($report['record']->class)->toBe(AIResponseException::class)
        ->and($report['record']->line)->toBe($exception->getLine())
        ->and($report['context']['processing']['job_id'])->toBe($jobId)
        ->and($report['context']['processing']['task_id'])->not->toBeEmpty()
        ->and($report['context']['processing']['file_id'])->toBe($file->id)
        ->and($report['context']['processing']['user_id'])->toBe($file->user_id)
        ->and($report['context']['processing']['provider'])->toBe('gemini')
        ->and($report['context']['processing_stage'])->toBe('extraction')
        ->and($report['context']['queue_failure']['queue'])->toBe('files')
        ->and($report['context']['queue_failure']['connection'])->toBe('database')
        ->and($report['context']['queue_failure']['attempt'])->toBe(1)
        ->and($report['context']['processing_failure']['field'])->toBe('return_policies')
        ->and($report['context']['processing_failure']['dates'])->toBe(['return_deadline' => '30 days'])
        ->and($report['context']['processing_failure']['errors'])->toBe($exception->context['errors'])
        ->and($report['context']['processing_failure']['retryable'])->toBeFalse()
        ->and($report['context']['processing_failure']['soft'])->toBeFalse()
        ->and($report['context']['processing_failure']['stage'])->toBe('extraction')
        ->and(json_encode($report['context']))->not->toContain('secret', 'private receipt')
        ->and($file->fresh()->status)->toBe('failed');
    $this->assertDatabaseCount('failed_jobs', 1);
    $this->assertDatabaseCount('jobs', 0);
});

it('reports manually failed jobs as unhandled and captures each new failure', function (): void {
    for ($attempt = 0; $attempt < 2; $attempt++) {
        ManualWorkerFailureReportingJob::dispatch()->onConnection('database')->onQueue('default');
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0, '--no-interaction' => true])->assertSuccessful();
    }

    expect($this->reports)->toHaveCount(2);
    foreach ($this->reports as $report) {
        expect($report['exception']->getMessage())->toBe('Manually failed worker')
            ->and($report['record']->handled)->toBeFalse()
            ->and($report['context']['queue_failure']['job_class'])->toBe(ManualWorkerFailureReportingJob::class)
            ->and($report['context']['queue_failure']['job_uuid'])->not->toBeEmpty()
            ->and($report['context']['queue_failure']['attempt'])->toBe(1)
            ->and($report['context'])->not->toHaveKey('processing_failure');
        app(ExceptionHandler::class)->report($report['exception']);
    }
    expect($this->reports)->toHaveCount(2);
    $this->assertDatabaseCount('failed_jobs', 2);
});

it('keeps daily budget waits queued across repeated days and resumes the same job without failures', function (): void {
    config(['ai.limits.max_calls_per_user_day' => 1]);
    $file = File::factory()->create(['status' => 'processing']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id, 'fileGuid' => $file->guid]);
    DailyAllowanceWorkerJob::dispatch($jobId)->allOnConnection('database')->allOnQueue('files')
        ->chain([new ManualWorkerFailureReportingJob]);

    for ($day = 0; $day < 6; $day++) {
        ProcessingUsageBudget::run($file->user_id, 'other-'.$day, 'extraction', fn () => ProcessingUsageBudget::reserve(1));
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0])->assertSuccessful();
        $file->refresh()->load('processingJobs.tasks');
        expect($file->status)->toBe('pending')
            ->and($file->processingSummary()['state'])->toBe('waiting')
            ->and($file->processingSummary()['resume_at'])->toBe(now()->utc()->addDay()->startOfDay()->addMinutes(5)->toIso8601String())
            ->and($this->reports)->toBeEmpty();
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 1);
        $this->travelTo(now()->utc()->addDay()->startOfDay()->addMinutes(6));
    }

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0])->assertSuccessful();
    expect($file->fresh()->status)->toBe('completed')
        ->and($file->fresh()->meta)->not->toHaveKey('processing_wait')
        ->and(JobHistory::query()->where('parent_uuid', $jobId)->value('status'))->toBe('completed');
    $this->assertDatabaseCount('failed_jobs', 0);
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'files']);
});

it('fails an exhausted per-run budget without scheduling a daily allowance retry', function (): void {
    $file = File::factory()->create(['status' => 'processing']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id, 'fileExtension' => 'pdf', 's3OriginalPath' => 'source.pdf']);
    config(['ai.limits.max_tokens_per_run' => 1]);
    $this->mock(WorkerFileManager::class)->shouldReceive('processWithCleanup')->once()
        ->andReturnUsing(fn () => ProcessingUsageBudget::run($file->user_id, $jobId, 'extraction', fn () => ProcessingUsageBudget::reserve(2)));
    ProcessFileGemini::dispatch($jobId)->onConnection('database')->onQueue('files');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0])->assertSuccessful();

    expect($file->fresh()->status)->toBe('failed')
        ->and($file->fresh()->meta['last_processing_error']['retryable'])->toBeFalse()
        ->and($file->fresh()->meta['last_processing_error']['retry_after'])->toBeNull()
        ->and($file->fresh()->meta)->not->toHaveKey('processing_wait')
        ->and($this->reports)->toHaveCount(1);
    $this->assertDatabaseCount('failed_jobs', 1);
    $this->assertDatabaseCount('jobs', 0);
});

class DailyAllowanceWorkerJob extends ProcessFileGemini
{
    protected function handleJob(): void
    {
        $file = File::withoutGlobalScope('user')->findOrFail($this->getMetadata()['fileId']);
        ProcessingUsageBudget::run($file->user_id, $this->jobID, 'extraction', function (): void {
            ProcessingUsageBudget::reserve(10);
            ProcessingUsageBudget::record(5, 2);
        });
        $file->update(['status' => 'completed']);
    }
}

class ManualWorkerFailureReportingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $this->fail(new RuntimeException('Manually failed worker'));
    }
}
