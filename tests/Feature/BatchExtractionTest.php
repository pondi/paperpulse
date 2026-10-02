<?php

use App\Jobs\Files\ProcessBatchItem;
use App\Models\BatchJob;
use App\Models\File;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\BatchProcessingService;
use App\Services\OCR\OCRResult;
use App\Services\OCR\OCRServiceFactory;
use App\Services\OCR\Providers\TextractProvider;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    config(['ai.ocr.options.cache_results' => false]);
    OCRServiceFactory::clearCache();
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create([
        'user_id' => $this->owner->id, 'file_type' => 'document', 'fileExtension' => 'pdf',
        's3_original_path' => 'documents/'.$this->owner->id.'/original.pdf',
    ]);
    Storage::disk('paperpulse')->put($this->file->s3_original_path, '%PDF-1.4 stored document');
    $this->ocr = Mockery::mock(TextractProvider::class);
    $this->ocr->shouldReceive('getSupportedExtensions')->andReturn(['pdf', 'png', 'jpg', 'jpeg']);
    $this->ocr->shouldReceive('getProviderName')->andReturn('textract');
    $this->app->instance(TextractProvider::class, $this->ocr);
});

afterEach(function (): void {
    OCRServiceFactory::clearCache();
});

it('executes an owned item through the database queue and extraction interface', function (string $type): void {
    config(['queue.default' => 'database']);
    $this->file->update(['file_type' => $type]);
    $this->ocr->shouldReceive('extractText')->once()->withArgs(function (string $path, string $fileType, string $guid) use ($type): bool {
        return is_file($path) && $fileType === $type && $guid === $this->file->guid;
    })->andReturn(OCRResult::success('Stored document text', 'textract'));
    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive($type === 'receipt' ? 'analyzeReceipt' : 'analyzeDocument')->once()
        ->with('Stored document text', ['quality' => 'high'])
        ->andReturn(['success' => true, 'data' => ['summary' => 'Extracted result'], 'cost' => 0.25]);
    $this->app->instance(AIService::class, $ai);

    $batch = app(BatchProcessingService::class)->processBatch(
        [['file_id' => $this->file->id]], $this->owner, $type, ['quality' => 'high'],
    );
    $job = Queue::connection('database')->pop('batch-small');
    expect($job)->not->toBeNull();
    $job->fire();

    $item = $batch->items()->firstOrFail();
    expect($item->status)->toBe('completed')->and($item->source)->toBe((string) $this->file->id)
        ->and($item->result)->toBe(['summary' => 'Extracted result'])
        ->and($batch->fresh()->status)->toBe('completed')->and((float) $batch->fresh()->actual_cost)->toBe(0.25);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Storage::disk('paperpulse')->assertExists($this->file->s3_original_path);
})->with(['receipt', 'document']);

it('validates owned file IDs and supported types at HTTP and service boundaries', function (string $case): void {
    Queue::fake();
    Sanctum::actingAs($this->owner);
    $items = [['file_id' => $this->file->id]];
    $type = 'document';
    if ($case === 'foreign') {
        $items[0]['file_id'] = File::factory()->create(['file_type' => 'document'])->id;
    } elseif ($case === 'deleted') {
        $this->file->delete();
    } elseif ($case === 'missing') {
        $items[0]['file_id'] = 999999;
    } elseif ($case === 'path') {
        $items = [['source' => '/etc/passwd']];
    } elseif ($case === 'url') {
        $items = [['source' => 'https://example.com/document.pdf']];
    } elseif ($case === 'type') {
        $type = 'unknown';
    } elseif ($case === 'mismatched item') {
        $items[0]['type'] = 'receipt';
    } else {
        $this->file->update(['file_type' => 'receipt']);
    }

    $this->postJson('/api/batch', compact('items', 'type'))->assertUnprocessable();
    expect(fn () => app(BatchProcessingService::class)->processBatch($items, $this->owner, $type))
        ->toThrow(ValidationException::class);
    expect(BatchJob::count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['foreign', 'deleted', 'missing', 'path', 'url', 'type', 'mismatched item', 'mismatched file']);

it('rechecks file ownership in workers before reading storage', function (): void {
    Queue::fake();
    $batch = app(BatchProcessingService::class)->processBatch([['file_id' => $this->file->id]], $this->owner);
    $this->file->update(['user_id' => User::factory()->create()->id]);
    $this->ocr->shouldNotReceive('extractText');
    Queue::assertPushed(ProcessBatchItem::class, function (ProcessBatchItem $job): bool {
        $job->handle();

        return true;
    });
    expect($batch->items()->first()->status)->toBe('failed')
        ->and($batch->items()->first()->error_message)->not->toBeEmpty()
        ->and($batch->fresh()->status)->toBe('completed_with_errors');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('reports OCR failures per item and cleans working files', function (): void {
    Queue::fake();
    $this->ocr->shouldReceive('extractText')->once()->andReturn(OCRResult::failure('Corrupt document', 'textract'));
    $batch = app(BatchProcessingService::class)->processBatch([['file_id' => $this->file->id]], $this->owner);
    Queue::assertPushed(ProcessBatchItem::class, function (ProcessBatchItem $job): bool {
        $job->handle();

        return true;
    });
    expect($batch->items()->first()->status)->toBe('failed')
        ->and($batch->items()->first()->error_message)->toContain('Corrupt document');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('uses converted Office PDFs and rejects unsupported originals', function (): void {
    $this->file->update(['fileExtension' => 'docx']);
    $this->ocr->shouldNotReceive('extractText');
    expect(fn () => app(TextExtractionService::class)->extractFromFile($this->file->id, $this->owner->id, 'document'))
        ->toThrow(Exception::class, 'Office files must finish conversion first');

    $this->file->update(['s3_archive_path' => 'documents/'.$this->owner->id.'/archive.pdf']);
    Storage::disk('paperpulse')->put($this->file->s3_archive_path, '%PDF-1.4 converted Office document');
    $this->ocr->shouldReceive('extractText')->once()->withArgs(fn (string $path): bool => str_ends_with($path, '.pdf'))
        ->andReturn(OCRResult::success('Converted text', 'textract'));
    expect(app(TextExtractionService::class)->extractFromFile($this->file->id, $this->owner->id, 'document'))->toBe('Converted text');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
