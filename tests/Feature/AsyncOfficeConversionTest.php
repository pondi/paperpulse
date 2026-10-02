<?php

use App\Jobs\Files\ConvertOfficeFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\FileConversion;
use App\Models\JobHistory;
use App\Services\Documents\ConversionService;
use App\Services\Documents\LocalOfficeConverter;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function prepareAsyncOfficePipeline(): ProcessFile
{
    $file = createOfficeConversionFile();
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $file->id, 'fileExtension' => 'docx', 'fileType' => 'document',
        'userId' => $file->user_id, 'fileGuid' => $file->guid, 's3OriginalPath' => $file->s3_original_path, 'filePath' => '/original.docx', 'processingProvider' => 'gemini']);
    (new FileJobChainDispatcher)->dispatch($id, 'document');
    $first = null;
    Bus::assertChained([function (ProcessFile $job) use (&$first): bool {
        $first = $job;

        return true;
    }, ProcessFileGemini::class, DeleteWorkingFiles::class]);

    return $first;
}

beforeEach(function (): void {
    Bus::fake();
    config(['processing.conversion.driver' => 'local', 'ai.file_processing_provider' => 'gemini']);
});

it('releases preprocessing before conversion and resumes a durable PDF extraction plan exactly once', function (): void {
    $first = prepareAsyncOfficePipeline();
    $this->mock(TextExtractionService::class)->shouldNotReceive('extract');
    $first->handle();
    $conversion = FileConversion::firstOrFail();
    expect($conversion->status)->toBe('pending')->and($first->chained)->toHaveCount(3);
    Bus::assertNotDispatched(ConvertOfficeFile::class);
    $step = unserialize($first->chained[0]);
    expect($step)->toBeInstanceOf(ConvertOfficeFile::class)->and($step->queue)->toBe('conversions');
    expect(JobHistory::where('uuid', $first->jobID)->first()->status)->toBe('processing');
    $this->mock(StorageService::class)->shouldReceive('getFile')->once()->andReturn(conversionDocxFixture())->shouldReceive('storeFile')->once()->andReturn($conversion->output_s3_path);
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()->andReturnUsing(fn ($source, $output) => file_put_contents($output, conversionPdfFixture()));
    Cache::flush();
    $step->handle();
    $step->handle();
    $metadata = JobMetadataPersistence::retrieve($first->jobID);
    expect($conversion->refresh()->status)->toBe('completed')->and($metadata['fileExtension'])->toBe('pdf');
    expect($metadata['s3OriginalPath'])->not->toBe($metadata['s3ArchivePath'])->and($metadata['filePath'])->toBeNull();
    expect(JobHistory::where('uuid', $step->uuid)->count())->toBe(1);
    expect(JobHistory::where('uuid', $first->jobID)->first()->status)->toBe('processing');
});

it('retains a retryable conversion failure without continuing unsupported originals', function (): void {
    $first = prepareAsyncOfficePipeline();
    $first->handle();
    $conversion = FileConversion::firstOrFail();
    $step = unserialize($first->chained[0]);
    $this->mock(StorageService::class)->shouldReceive('getFile')->once()->andReturn(conversionDocxFixture())->shouldNotReceive('storeFile');
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()->andThrow(new RuntimeException('Unavailable'));
    expect(fn () => $step->handle())->toThrow(RuntimeException::class, 'Unavailable');
    $step->failed(new RuntimeException('Unavailable'));
    $metadata = JobMetadataPersistence::retrieve($first->jobID);
    expect($metadata['fileExtension'])->toBe('docx')->and(isset($metadata['s3ArchivePath']))->toBeFalse();
    expect($conversion->refresh()->status)->toBe('failed')->and($conversion->file->status)->toBe('failed');
    expect(JobHistory::where('parent_uuid', $first->jobID)->where('status', 'cancelled')->count())->toBe(2);
    expect(app(ConversionService::class)->retry($conversion))->toBeTrue();
    Bus::assertDispatched(ConvertOfficeFile::class, fn ($job) => $job->uuid === $step->uuid && count($job->chained) === 2);
});

it('reconciles abandoned conversion handoffs without losing the original planned resume', function (): void {
    $first = prepareAsyncOfficePipeline();
    $first->handle();
    $conversion = FileConversion::firstOrFail();
    $this->travel(7)->minutes();
    Bus::fake();
    expect(app(ConversionService::class)->reconcileAbandoned())->toBe(1);
    expect(app(ConversionService::class)->reconcileAbandoned())->toBe(0);
    Bus::assertDispatched(ConvertOfficeFile::class, 1);
    expect($conversion->refresh()->status)->toBe('pending')->and($conversion->retry_count)->toBe(1);
});

it('restores the conversion barrier on a completed preprocessing redelivery with its original queue payload', function (): void {
    $first = prepareAsyncOfficePipeline();
    $originalPayload = serialize($first);
    $first->handle();
    $redelivery = unserialize($originalPayload);
    $redelivery->handle();
    $conversion = unserialize($redelivery->chained[0]);
    expect($conversion)->toBeInstanceOf(ConvertOfficeFile::class);
    expect(JobHistory::where('parent_uuid', $first->jobID)->where('name', 'ConvertOfficeFile')->count())->toBe(1);
    Bus::fake();
    $redelivery->dispatchNextJobInChain();
    Bus::assertDispatched(ConvertOfficeFile::class, 1);
    Bus::assertNotDispatched(ProcessFileGemini::class);
});

it('rechecks the handoff age after locking a request selected by a concurrent reconciler', function (): void {
    $first = prepareAsyncOfficePipeline();
    $first->handle();
    $conversion = FileConversion::firstOrFail();
    $this->travel(7)->minutes();
    $simulate = true;
    FileConversion::retrieved(function (FileConversion $selected) use (&$simulate, $conversion): void {
        if ($simulate && $selected->id === $conversion->id) {
            $simulate = false;
            $selected->getConnection()->table($selected->getTable())->where('id', $selected->id)
                ->update(['updated_at' => now(), 'retry_count' => 1]);
        }
    });
    Bus::fake();
    expect(app(ConversionService::class)->reconcileAbandoned())->toBe(0);
    expect($conversion->refresh()->retry_count)->toBe(1);
    Bus::assertNothingDispatched();
});

it('keeps slow conversion on the database queue and releases the preprocessing worker', function (): void {
    $first = prepareAsyncOfficePipeline();
    $this->mock(TextExtractionService::class)->shouldNotReceive('extract');
    $started = microtime(true);
    $first->handle();
    expect(microtime(true) - $started)->toBeLessThan(1.0);
    $conversion = FileConversion::firstOrFail();
    $step = unserialize($first->chained[0]);
    expect($step->connection)->toBe('database');
    $queueId = app('queue')->connection($step->connection)->push($step, queue: $step->queue);
    $payload = $conversion->getConnection()->table('jobs')->where('id', $queueId)->first();
    expect($payload->queue)->toBe('conversions');
    $this->travel(2)->minutes();
    expect($conversion->refresh()->status)->toBe('pending');
    Bus::assertNotDispatched(ProcessFileGemini::class);
    $this->mock(StorageService::class)->shouldReceive('getFile')->once()->andReturn(conversionDocxFixture())
        ->shouldReceive('storeFile')->once()->andReturn($conversion->output_s3_path);
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()->andReturnUsing(function ($source, $output): void {
        $this->travel(2)->minutes();
        file_put_contents($output, conversionPdfFixture());
    });
    $step->handle();
    $step->handle();
    $step->dispatchNextJobInChain();
    Bus::assertDispatched(ProcessFileGemini::class, 1);
    expect(JobMetadataPersistence::retrieve($first->jobID)['fileExtension'])->toBe('pdf');
});
