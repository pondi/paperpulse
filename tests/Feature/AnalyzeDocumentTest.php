<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Models\Document;
use App\Models\File;
use App\Models\JobHistory;
use App\Services\DocumentAnalysisService;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('bounds analyzed provider titles and preserves complete evidence', function (?string $title, string $expectedTitle): void {
    Queue::fake();
    $file = File::factory()->create(['file_type' => 'document', 'status' => 'processing']);
    $document = Document::factory()->create([
        'file_id' => $file->id,
        'user_id' => $file->user_id,
        'title' => 'Original title',
        'metadata' => ['source' => 'scanner', 'userId' => 0],
    ]);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id]);
    $analysis = ['title' => $title, 'summary' => 'Analyzed summary', 'entities' => [['type' => 'person', 'name' => 'Ada']]];
    $this->mock(DocumentAnalysisService::class)->shouldReceive('analyze')->once()->andReturn($analysis);

    $job = new AnalyzeDocument($jobId);
    $job->handle();

    $document->refresh();
    expect($document->title)->toBe($expectedTitle)
        ->and($document->metadata['ai_analysis'])->toBe($analysis)
        ->and($document->metadata['source'])->toBe('scanner')
        ->and($document->metadata['entities'])->toBe($analysis['entities'])
        ->and($file->fresh()->status)->toBe('completed')
        ->and(JobHistory::where('uuid', $job->uuid)->firstOrFail()->status)->toBe('completed');
})->with([
    'short title' => ['Analyzed title', 'Analyzed title'],
    'database limit' => [str_repeat('ø', 255), str_repeat('ø', 255)],
    'long multibyte title' => [str_repeat('ø', 300), str_repeat('ø', 255)],
    'missing provider title' => [null, 'Original title'],
]);
