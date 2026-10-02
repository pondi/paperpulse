<?php

use App\Jobs\Files\ProcessBatchItem;
use App\Models\BatchItem;
use App\Models\File;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\BatchProcessingService;
use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->files = File::factory()->count(5)->create(['user_id' => $this->owner->id, 'file_type' => 'document']);
    $this->text = Mockery::mock(TextExtractionService::class);
    $this->app->instance(TextExtractionService::class, $this->text);
    $this->ai = Mockery::mock(AIService::class);
    $this->app->instance(AIService::class, $this->ai);
    $this->batch = app(BatchProcessingService::class)->processBatch(
        $this->files->map(fn (File $file): array => ['file_id' => $file->id])->all(),
        $this->owner, 'document', ['batch_size' => 2],
    );
    $this->chunks = Queue::pushed(ProcessBatchItem::class)->all();
});

it('finishes every immutable chunk in any order without repeating extraction or cost', function (): void {
    $this->text->shouldReceive('extractFromFile')->times(5)->andReturn('File text');
    $this->ai->shouldReceive('analyzeDocument')->times(5)->andReturn([
        'success' => true, 'data' => ['summary' => 'Analyzed'], 'cost' => 0.15,
    ]);
    expect($this->chunks)->toHaveCount(3);

    foreach (array_reverse($this->chunks) as $chunk) {
        unserialize(serialize($chunk))->handle();
        $chunk->handle();
    }
    app(BatchProcessingService::class)->updateBatchProgress($this->batch->id);

    expect($this->batch->items()->where('status', 'completed')->count())->toBe(5)
        ->and($this->batch->fresh()->processed_items)->toBe(5)
        ->and($this->batch->fresh()->failed_items)->toBe(0)
        ->and((float) $this->batch->fresh()->actual_cost)->toBe(0.75)
        ->and($this->batch->fresh()->status)->toBe('completed');
});

it('counts per-item failures once while later chunks still finish', function (): void {
    $this->text->shouldReceive('extractFromFile')->once()->with($this->files[0]->id, $this->owner->id, 'document')
        ->andThrow(new RuntimeException('Unreadable file'));
    foreach ($this->files->slice(1) as $file) {
        $this->text->shouldReceive('extractFromFile')->once()->with($file->id, $this->owner->id, 'document')->andReturn('File text');
    }
    $this->ai->shouldReceive('analyzeDocument')->times(4)->andReturn([
        'success' => true, 'data' => ['summary' => 'Analyzed'], 'cost' => 0.15,
    ]);
    foreach ($this->chunks as $chunk) {
        $chunk->handle();
        $chunk->handle();
    }

    expect($this->batch->fresh()->processed_items)->toBe(5)
        ->and($this->batch->fresh()->failed_items)->toBe(1)
        ->and((float) $this->batch->fresh()->actual_cost)->toBe(0.6)
        ->and($this->batch->fresh()->status)->toBe('completed_with_errors');
});

it('terminal chunk failure affects only its own unprocessed items', function (): void {
    $this->text->shouldReceive('extractFromFile')->times(3)->andReturn('File text');
    $this->ai->shouldReceive('analyzeDocument')->times(3)->andReturn([
        'success' => true, 'data' => ['summary' => 'Analyzed'], 'cost' => 0.15,
    ]);
    $this->chunks[1]->handle();
    $this->chunks[1]->failed(new RuntimeException('Duplicate delivery failed'));
    $this->chunks[0]->failed(new RuntimeException('Worker exhausted retries'));
    $this->chunks[0]->failed(new RuntimeException('Repeated failure callback'));
    $this->chunks[0]->handle();
    $this->chunks[2]->handle();

    expect($this->batch->items()->where('status', 'completed')->count())->toBe(3)
        ->and($this->batch->items()->where('status', 'failed')->count())->toBe(2)
        ->and($this->batch->fresh()->processed_items)->toBe(5)
        ->and($this->batch->fresh()->failed_items)->toBe(2)
        ->and((float) $this->batch->fresh()->actual_cost)->toBe(0.45);
});

it('resumes a partially completed chunk after a worker interruption', function (): void {
    $this->text->shouldReceive('extractFromFile')->times(5)->andReturn('File text');
    $this->ai->shouldReceive('analyzeDocument')->times(5)->andReturn([
        'success' => true, 'data' => ['summary' => 'Analyzed'], 'cost' => 0.15,
    ]);
    BatchItem::retrieved(function (BatchItem $item): void {
        if ($item->item_index === 1) {
            throw new RuntimeException('Worker interrupted before the next item');
        }
    });
    try {
        expect(fn () => $this->chunks[0]->handle())->toThrow(RuntimeException::class, 'Worker interrupted');
    } finally {
        BatchItem::flushEventListeners();
    }
    expect($this->batch->items()->where('status', 'completed')->count())->toBe(1);
    foreach ($this->chunks as $chunk) {
        $chunk->handle();
    }
    expect($this->batch->fresh()->processed_items)->toBe(5)
        ->and((float) $this->batch->fresh()->actual_cost)->toBe(0.75)
        ->and($this->batch->fresh()->status)->toBe('completed');
});
