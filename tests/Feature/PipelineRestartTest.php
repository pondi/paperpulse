<?php

use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Files\ClassifyFile;
use App\Jobs\Files\ConvertOfficeFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Models\FileConversion;
use App\Models\JobHistory;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FileReprocessingService;
use App\Services\JobChainService;
use App\Services\Jobs\JobChainPlan;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('restarts the stored pipeline at its failed step for its exact file', function (string $provider, string $type, string $extension, int $failedIndex, array $expected): void {
    Bus::fake();
    $file = File::factory()->create(['file_type' => $type, 'fileExtension' => $extension]);
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, [
        'fileId' => $file->id, 'userId' => $file->user_id, 'fileExtension' => $extension,
        'processingProvider' => $provider, 'pipeline' => $extension === 'csv' ? 'csv' : $provider,
    ]);
    (new FileJobChainDispatcher)->dispatch($id, $type);
    $parent = JobHistory::where('uuid', $id)->first();
    $plan = $parent->metadata['plannedSteps'];
    foreach ($plan as $index => $step) {
        JobHistory::where('uuid', $step['uuid'])->update(['status' => $index < $failedIndex ? 'completed' : ($index === $failedIndex ? 'failed' : 'cancelled')]);
    }
    $parent->update(['status' => 'failed', 'finished_at' => now()]);
    File::factory()->create();
    Cache::flush();
    config(['ai.file_processing_provider' => 'ocr-only']);
    Bus::fake();

    $result = (new JobChainService)->restartJobChain($id);

    expect($result['success'])->toBeTrue()->and($result['jobs_count'])->toBe(count($expected));
    Bus::assertChained($expected);
    Bus::assertDispatched($expected[0], fn ($job): bool => $job->jobID === $id && $job->uuid === $plan[$failedIndex]['uuid']);
    expect($parent->fresh()->metadata['fileId'])->toBe($file->id)
        ->and($parent->fresh()->metadata['pipeline'])->toBe($extension === 'csv' ? 'csv' : $provider)
        ->and($parent->fresh()->finished_at)->toBeNull()
        ->and($parent->tasks()->where('status', 'pending')->count())->toBe(count($expected));
})->with([
    ['gemini', 'document', 'pdf', 0, [ProcessFile::class, ProcessFileGemini::class, DeleteWorkingFiles::class]],
    ['gemini', 'receipt', 'pdf', 1, [ProcessFileGemini::class, DeleteWorkingFiles::class]],
    ['textract+openai', 'receipt', 'pdf', 1, [ClassifyFile::class, DeleteWorkingFiles::class]],
    ['textract+openai', 'document', 'pdf', 1, [ClassifyFile::class, DeleteWorkingFiles::class]],
    ['textract+openai', 'document', 'csv', 0, [ProcessCsvImport::class, DeleteWorkingFiles::class]],
]);

it('rejects missing foreign deleted and superseded source context without guessing another file', function (string $invalid): void {
    Bus::fake();
    $file = File::factory()->create();
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $file->id, 'fileExtension' => 'pdf']);
    (new FileJobChainDispatcher)->dispatch($id, 'document');
    $parent = JobHistory::where('uuid', $id)->first();
    $metadata = $parent->metadata;
    match ($invalid) {
        'missing' => $metadata = [],
        'owner' => $metadata['userId'] = File::factory()->create()->user_id,
        'pipeline' => $metadata['pipeline'] = 'unknown',
        'deleted' => $file->delete(),
        'generation' => $file->update(['meta' => ['processing_generation' => 'newer']]),
    };
    $parent->update(['metadata' => $metadata]);
    File::factory()->create();
    Cache::flush();
    Bus::fake();

    expect((new JobChainService)->restartJobChain($id)['success'])->toBeFalse();
    Bus::assertNothingDispatched();
})->with(['missing', 'owner', 'pipeline', 'deleted', 'generation']);

it('preserves the last pipeline when preparing reprocessing metadata', function (): void {
    $file = File::factory()->create();
    JobMetadataPersistence::store((string) Str::uuid(), ['fileId' => $file->id, 'processingProvider' => 'gemini']);
    config(['ai.file_processing_provider' => 'textract+openai']);
    $service = app(FileReprocessingService::class);
    $method = new ReflectionMethod($service, 'prepareReprocessingMetadata');
    $metadata = $method->invoke($service, $file, (string) Str::uuid(), 'Reprocess');

    expect($metadata['processingProvider'])->toBe('gemini')->and($metadata['pipeline'])->toBe('gemini');
});

it('restores a dynamically inserted conversion from its exact durable resume context', function (): void {
    Bus::fake();
    $file = File::factory()->create(['fileExtension' => 'docx']);
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $file->id, 'fileExtension' => 'docx', 'processingProvider' => 'gemini']);
    (new FileJobChainDispatcher)->dispatch($id, 'document');
    $parent = JobHistory::where('uuid', $id)->first();
    $first = $parent->tasks()->first();
    $conversion = FileConversion::create([
        'file_id' => $file->id, 'user_id' => $file->user_id, 'input_extension' => 'docx',
        'input_s3_path' => 'original.docx', 'output_s3_path' => 'archive.pdf', 'status' => 'failed',
    ]);
    $job = new ConvertOfficeFile($conversion->id, $id);
    $job->onQueue('conversions');
    $conversion->update(['metadata' => ['chain_id' => $id, 'resume_payload' => base64_encode(serialize($job))]]);
    JobChainPlan::prependStep($id, $first->uuid, $job);
    $first->update(['status' => 'completed']);
    JobHistory::where('uuid', $job->uuid)->update(['status' => 'failed']);
    Bus::fake();

    expect((new JobChainService)->restartJobChain($id)['success'])->toBeTrue();
    Bus::assertChained([ConvertOfficeFile::class, ProcessFileGemini::class, DeleteWorkingFiles::class]);
    Bus::assertDispatched(ConvertOfficeFile::class, fn (ConvertOfficeFile $restored): bool => $restored->conversionId === $conversion->id && $restored->uuid === $job->uuid);
});
