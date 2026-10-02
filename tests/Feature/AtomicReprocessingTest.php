<?php

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\Document;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\Receipt;
use App\Services\DocumentAnalysisService;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FileReprocessingService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\StorageService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class AtomicReceiptReplacementJob extends ProcessReceipt
{
    public bool $shouldFail = false;

    protected function handleJob(): void
    {
        $metadata = $this->getMetadata();
        Receipt::factory()->create(['user_id' => $metadata['userId'], 'file_id' => $metadata['fileId'], 'total_amount' => 200]);
        if ($this->shouldFail) {
            throw new RuntimeException('Extraction failed');
        }
    }
}

beforeEach(function (): void {
    Bus::fake();
    $this->file = File::factory()->create(['status' => 'completed', 's3_original_path' => 'original.pdf']);
    $this->receipt = Receipt::factory()->create(['file_id' => $this->file->id, 'user_id' => $this->file->user_id, 'total_amount' => 100]);
    $this->storage = Mockery::mock(StorageService::class);
    $this->storage->shouldReceive('getFile')->andReturn('source');
    $this->dispatcher = Mockery::mock(FileJobChainDispatcher::class);
});

it('retains the prior extraction generation and preview when queue dispatch fails', function (): void {
    $this->file->update(['meta' => ['processing_generation' => 'prior'], 's3_image_path' => 'preview.jpg']);
    $this->dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue offline'));
    $result = (new FileReprocessingService($this->storage, $this->dispatcher))->reprocessFile($this->file, true);
    expect($result['success'])->toBeFalse()->and($this->file->fresh()->meta['processing_generation'])->toBe('prior')
        ->and($this->file->fresh()->status)->toBe('completed')->and($this->file->fresh()->s3_image_path)->toBe('preview.jpg');
    $this->assertNotSoftDeleted($this->receipt);
    expect(JobHistory::count())->toBe(0);
});

it('replaces all prior results atomically and duplicate delivery keeps one valid result set', function (): void {
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $this->file->id, 'metadata' => ['reprocessing' => true]]);
    $job = new AtomicReceiptReplacementJob($id);
    $job->handle();
    $job->handle();
    $this->assertSoftDeleted($this->receipt);
    expect(Receipt::where('file_id', $this->file->id)->count())->toBe(1)
        ->and(Receipt::where('file_id', $this->file->id)->first()->total_amount)->toBe('200.00');
});

it('rolls back a failed staged replacement while preserving the last successful result', function (): void {
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $this->file->id, 'metadata' => ['reprocessing' => true]]);
    $job = new AtomicReceiptReplacementJob($id);
    $job->shouldFail = true;
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Extraction failed');
    $this->assertNotSoftDeleted($this->receipt);
    expect(Receipt::where('file_id', $this->file->id)->count())->toBe(1)
        ->and($this->receipt->fresh()->total_amount)->toBe('100.00');
});

it('rejects a superseded generation and its failure callback without changing current results', function (): void {
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $this->file->id, 'metadata' => ['reprocessing' => true]]);
    $job = new AtomicReceiptReplacementJob($id);
    $this->file->refresh()->update(['meta' => ['processing_generation' => 'newer']]);
    $job->handle();
    $job->failed(new RuntimeException('Old worker failed'));
    $this->assertNotSoftDeleted($this->receipt);
    expect(Receipt::where('file_id', $this->file->id)->count())->toBe(1)->and($this->file->fresh()->status)->toBe('completed');
});

it('keeps an OCR document draft invisible until final analysis commits', function (): void {
    $this->receipt->delete();
    $old = Document::factory()->create(['file_id' => $this->file->id, 'user_id' => $this->file->user_id, 'title' => 'Prior']);
    $id = (string) Str::uuid();
    JobMetadataPersistence::store($id, ['fileId' => $this->file->id, 'metadata' => ['reprocessing' => true]]);
    $processor = new ProcessDocument($id);
    (new ReflectionMethod($processor, 'createDocumentRecord'))->invoke($processor, $this->file, null, 'A newly extracted document body', 'Reprocess', true);
    expect(Document::where('file_id', $this->file->id)->count())->toBe(1)->and($old->fresh()->title)->toBe('Prior');
    $analysis = Mockery::mock(DocumentAnalysisService::class);
    $analysis->shouldReceive('analyze')->once()->andThrow(new RuntimeException('Provider offline'));
    app()->instance(DocumentAnalysisService::class, $analysis);
    $job = new AnalyzeDocument($id);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Provider offline');
    $this->assertNotSoftDeleted($old);
    $analysis = Mockery::mock(DocumentAnalysisService::class);
    $analysis->shouldReceive('analyze')->once()->andReturn(['title' => 'Replacement']);
    app()->instance(DocumentAnalysisService::class, $analysis);
    $job->handle();
    $this->assertSoftDeleted($old);
    expect(Document::where('file_id', $this->file->id)->count())->toBe(1)
        ->and(Document::where('file_id', $this->file->id)->first()->title)->toBe('Replacement');
});

it('assigns a fresh generation while keeping prior results after an accepted handoff', function (): void {
    $this->file->update(['meta' => ['processing_generation' => 'prior']]);
    $this->dispatcher->shouldReceive('dispatch')->once();
    $result = (new FileReprocessingService($this->storage, $this->dispatcher))->reprocessFile($this->file, true);
    expect($result['success'])->toBeTrue()->and($this->file->fresh()->meta['processing_generation'])->not->toBe('prior');
    $this->assertNotSoftDeleted($this->receipt);
    expect(JobHistory::where('uuid', $result['jobId'])->value('metadata')['processingGeneration'])
        ->toBe($this->file->fresh()->meta['processing_generation']);
});
