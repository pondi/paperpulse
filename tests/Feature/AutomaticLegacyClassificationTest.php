<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ClassifyFile;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Models\JobHistory;
use App\Services\AI\TypeClassification\AutomaticTypeResolver;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Bus::fake();
    Http::preventStrayRequests();
    config(['ai.file_processing_provider' => 'textract+openai']);
});

it('replaces the upload hint with the detected type and persists the correct downstream extraction steps', function (string $uploaded, string $detected, array $classes): void {
    $file = File::factory()->create(['file_type' => $uploaded]);
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $file->id, 'userId' => $file->user_id, 'fileType' => $uploaded,
        'fileExtension' => 'pdf', 'extractedText' => 'Source document content', 'metadata' => []]);
    app(FileJobChainDispatcher::class)->dispatch($id, $uploaded);
    $plan = JobHistory::query()->where('uuid', $id)->firstOrFail()->metadata['plannedSteps'];
    $this->mock(AutomaticTypeResolver::class)->shouldReceive('fromText')->once()
        ->withArgs(fn (File $selected, string $text, string $run): bool => $selected->id === $file->id && $text === 'Source document content' && $run === $id)
        ->andReturn(new ClassificationResult($detected, .95, 'Source classification'));
    $job = new ClassifyFile($id);
    $job->uuid = $plan[1]['uuid'];
    $job->handle();
    $job->handle();
    $metadata = JobMetadataPersistence::retrieve($id);
    expect($file->fresh()->file_type)->toBe($detected)->and($metadata['fileType'])->toBe($detected)
        ->and(array_column($metadata['classificationSteps'], 'class'))->toBe($classes);
    $planned = collect($metadata['plannedSteps']);
    foreach ($classes as $class) {
        expect($planned->where('class', $class))->toHaveCount(1);
    }
})->with([
    ['receipt', 'document', [ProcessDocument::class, AnalyzeDocument::class]],
    ['document', 'receipt', [ProcessReceipt::class, MatchMerchant::class]],
]);
