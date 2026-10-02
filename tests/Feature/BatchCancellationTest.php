<?php

use App\Jobs\Files\ProcessBatchItem;
use App\Models\BatchJob;
use App\Models\File;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\BatchProcessingService;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->files = File::factory()->count(3)->create(['user_id' => $this->owner->id, 'file_type' => 'document']);
    $this->items = $this->files->map(fn (File $file): array => ['file_id' => $file->id])->all();
});

it('queues every chunk only after the enclosing transaction commits', function (): void {
    config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
    DB::beginTransaction();
    $batch = app(BatchProcessingService::class)->processBatch($this->items, $this->owner, 'document', ['batch_size' => 2]);
    $this->assertDatabaseCount('jobs', 0);
    expect($batch->items()->count())->toBe(3)->and($batch->fresh()->status)->toBe('processing');
    DB::commit();
    $this->assertDatabaseCount('jobs', 2);
});

it('does not queue chunks from a rolled back transaction', function (): void {
    config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
    DB::beginTransaction();
    app(BatchProcessingService::class)->processBatch($this->items, $this->owner, 'document', ['batch_size' => 2]);
    DB::rollBack();
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('batch_jobs', 0);
});

it('allows synchronous workers to see the complete committed batch', function (): void {
    config(['queue.default' => 'sync']);
    $text = Mockery::mock(TextExtractionService::class);
    $text->shouldReceive('extractFromFile')->times(3)->andReturnUsing(function (): string {
        $batch = BatchJob::firstOrFail();
        expect($batch->items()->count())->toBe(3)->and($batch->status)->toBe('processing');

        return 'Stored text';
    });
    $this->app->instance(TextExtractionService::class, $text);
    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive('analyzeDocument')->times(3)->andReturn(['success' => true, 'data' => ['summary' => 'Result']]);
    $this->app->instance(AIService::class, $ai);

    $batch = app(BatchProcessingService::class)->processBatch($this->items, $this->owner, 'document', ['batch_size' => 2]);
    expect($batch->fresh()->status)->toBe('completed')->and($batch->fresh()->processed_items)->toBe(3);
});

it('cancels pending extraction and keeps duplicate deliveries harmless', function (): void {
    Queue::fake();
    $text = Mockery::mock(TextExtractionService::class);
    $text->shouldNotReceive('extractFromFile');
    $this->app->instance(TextExtractionService::class, $text);
    $service = app(BatchProcessingService::class);
    $batch = $service->processBatch($this->items, $this->owner, 'document', ['batch_size' => 2]);
    expect($service->cancelBatch($batch->id, $this->owner))->toBeTrue()
        ->and($service->cancelBatch($batch->id, $this->owner))->toBeFalse();
    $cancelledAt = $batch->fresh()->completed_at;
    foreach (Queue::pushed(ProcessBatchItem::class) as $chunk) {
        $chunk->handle();
        $chunk->handle();
        $chunk->failed(new RuntimeException('Late failure callback'));
    }

    $status = $service->getBatchStatus($batch->id, $this->owner);
    expect($status['status'])->toBe('cancelled')->and($status['processed_items'])->toBe(0)
        ->and($status['failed_items'])->toBe(0)->and($status['cancelled_items'])->toBe(3)
        ->and($batch->fresh()->completed_at->equalTo($cancelledAt))->toBeTrue();
    expect($batch->items()->get()->every(fn ($item): bool => $item->isComplete()))->toBeTrue();
});

it('lets the running item finish while cancelling the remaining items', function (): void {
    Queue::fake();
    $service = app(BatchProcessingService::class);
    $batch = $service->processBatch($this->items, $this->owner, 'document', ['batch_size' => 3]);
    $text = Mockery::mock(TextExtractionService::class);
    $text->shouldReceive('extractFromFile')->once()->andReturnUsing(function () use ($service, $batch): string {
        expect($service->cancelBatch($batch->id, $this->owner))->toBeTrue();

        return 'Text already being extracted';
    });
    $this->app->instance(TextExtractionService::class, $text);
    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive('analyzeDocument')->once()->andReturn(['success' => true, 'data' => ['summary' => 'Result'], 'cost' => 0.1]);
    $this->app->instance(AIService::class, $ai);
    $chunk = Queue::pushed(ProcessBatchItem::class)->first();
    $chunk->handle();
    $chunk->handle();
    $chunk->failed(new RuntimeException('Late failure callback'));

    expect($batch->fresh()->status)->toBe('cancelled')->and($batch->fresh()->processed_items)->toBe(1)
        ->and($batch->fresh()->failed_items)->toBe(0)->and((float) $batch->fresh()->actual_cost)->toBe(0.1)
        ->and($batch->items()->where('status', 'cancelled')->count())->toBe(2);
});

it('does not reopen a cancelled batch when its last running item completes', function (): void {
    Queue::fake();
    $service = app(BatchProcessingService::class);
    $batch = $service->processBatch([$this->items[0]], $this->owner);
    $text = Mockery::mock(TextExtractionService::class);
    $text->shouldReceive('extractFromFile')->once()->andReturnUsing(function () use ($service, $batch): string {
        $service->cancelBatch($batch->id, $this->owner);

        return 'Text';
    });
    $this->app->instance(TextExtractionService::class, $text);
    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive('analyzeDocument')->once()->andReturn(['success' => true, 'data' => ['summary' => 'Result']]);
    $this->app->instance(AIService::class, $ai);
    Queue::pushed(ProcessBatchItem::class)->first()->handle();

    expect($batch->fresh()->status)->toBe('cancelled')->and($batch->fresh()->processed_items)->toBe(1);
});

it('rejects foreign and terminal batch cancellation', function (): void {
    Queue::fake();
    $service = app(BatchProcessingService::class);
    $batch = $service->processBatch($this->items, $this->owner);
    expect($service->cancelBatch($batch->id, User::factory()->create()))->toBeFalse()
        ->and($batch->fresh()->status)->toBe('processing');
    $batch->update(['status' => 'completed_with_errors']);
    expect($service->cancelBatch($batch->id, $this->owner))->toBeFalse()
        ->and($batch->fresh()->status)->toBe('completed_with_errors');
});
