<?php

use App\Models\File;
use App\Services\Files\FileReprocessingService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Exceptions::fake();
});

it('automatically requeues uncertain classification without a corrected type or clearing reusable stages', function (): void {
    $file = File::factory()->create(['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'uncertain_classification', 'corrected_type' => 'receipt']]]);
    $file->update(['updated_at' => now()->subMinutes(20)]);
    $this->mock(FileReprocessingService::class)->shouldReceive('reprocessFile')->once()
        ->withArgs(fn (File $selected, bool $force, ?string $provider, bool $fresh, bool $skipActive): bool => $selected->id === $file->id && ! $force && $provider === null && ! $fresh && $skipActive)
        ->andReturn(['success' => true, 'jobId' => 'recovery', 'message' => 'Queued']);
    $this->artisan('files:recover-automatic')->assertSuccessful();
    expect($file->fresh()->meta['automatic_recovery_attempts'])->toBe(1)
        ->and($file->fresh()->meta['review'])->not->toHaveKey('corrected_type');
});

it('leaves processing limits receipt totals and exhausted recovery attempts for their actual resolution', function (array $attributes): void {
    $file = File::factory()->create($attributes);
    $file->update(['updated_at' => now()->subMinutes(20)]);
    $this->mock(FileReprocessingService::class)->shouldNotReceive('reprocessFile');
    $this->artisan('files:recover-automatic')->assertSuccessful();
})->with([
    [['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'processing_limit']]]],
    [['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'receipt_totals']]]],
    [['status' => 'failed', 'meta' => ['last_processing_error' => ['retryable' => true], 'automatic_recovery_attempts' => 2]]],
    [['status' => 'failed', 'meta' => ['last_processing_error' => ['retryable' => true, 'retry_after' => now()->addDay()->toIso8601String()]]]],
]);

it('reports a failed automatic processing handoff for production monitoring', function (): void {
    $file = File::factory()->create(['status' => 'failed', 'meta' => ['last_processing_error' => ['retryable' => true]]]);
    $file->update(['updated_at' => now()->subMinutes(20)]);
    $this->mock(FileReprocessingService::class)->shouldReceive('reprocessFile')->once()->andReturn(['success' => false, 'jobId' => null, 'message' => 'Queue unavailable']);
    $this->artisan('files:recover-automatic')->assertSuccessful();
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Automatic file recovery could not dispatch processing.');
});
