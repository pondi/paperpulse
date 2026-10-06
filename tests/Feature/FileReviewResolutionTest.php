<?php

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\FileReprocessingService;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('retains uncertain classification for owner review without automatic provider retries', function () {
    $path = tempnam(sys_get_temp_dir(), 'review_').'.txt';
    file_put_contents($path, 'A short uncertain document');
    $file = File::factory()->create(['fileExtension' => 'txt']);
    $this->mock(WorkerFileManager::class, function ($mock) use ($path): void {
        $mock->shouldReceive('processWithCleanup')->once()->andReturnUsing(fn ($s3, $guid, $extension, $callback) => $callback($path));
    });
    $this->mock(GeminiFileManager::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')->once()->andReturn(['fileUri' => 'https://test/file', 'name' => 'files/test', 'mimeType' => 'text/plain']);
        $mock->shouldReceive('waitUntilActive')->once();
        $mock->shouldReceive('deleteFile')->once()->with('files/test')->andReturnTrue();
    });
    $this->mock(GeminiTypeClassifier::class, function ($mock): void {
        $mock->shouldReceive('classify')->once()->andReturn(new ClassificationResult('unknown', 0.3, 'Receipt or invoice; unclear heading'));
    });
    $metadata = ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileExtension' => 'txt', 's3OriginalPath' => 'source.txt'];
    $job = new class('review-test', $metadata) extends ProcessFileGemini
    {
        public function __construct(string $id, private array $metadata)
        {
            parent::__construct($id);
        }

        protected function getMetadata(): ?array
        {
            return $this->metadata;
        }

        protected function updateProgress(int $progress): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    try {
        $job->process();
        $job->process();
        expect($file->fresh()->status)->toBe('needs_review')
            ->and($file->fresh()->meta['review']['confidence'])->toBe(0.3)
            ->and($file->fresh()->meta['review']['classification']['document_type'])->toBe('unknown')
            ->and($file->extractableEntities()->count())->toBe(0);
    } finally {
        unlink($path);
    }
});

it('shows the owner review details and queues a targeted extraction with a fresh generation', function () {
    $this->withoutVite();
    Queue::fake();
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'status' => 'needs_review', 'meta' => ['review' => [
        'reason' => 'uncertain_classification', 'confidence' => 0.4, 'reasoning' => 'Please choose a type',
    ]]]);
    $this->actingAs($owner)->get(route('files.index', ['status' => 'needs_review']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Files/Index')->has('files.data', 1)
        ->where('files.data.0.review.confidence', 0.4)->where('stats.needs_review', 1)->has('reviewTypes'));
    $this->patch(route('files.change-type', $file), ['file_type' => 'invoice'])->assertRedirect();
    $fresh = $file->fresh();
    $metadata = JobHistory::where('file_id', $file->id)->firstOrFail()->metadata;
    expect($fresh->status)->toBe('pending')->and($fresh->meta['review']['confidence'])->toBe(0.4)
        ->and($fresh->meta['review']['corrected_type'])->toBe('invoice')
        ->and($metadata['targetedExtraction'])->toBeTrue()
        ->and($metadata['processingGeneration'])->toBe($fresh->meta['processing_generation']);
    Queue::assertPushed(ProcessFileGemini::class, 1);
});

it('rejects another user, unsupported corrections, and processing-limit bypasses', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'status' => 'needs_review', 'meta' => ['review' => ['reason' => 'processing_limit']]]);
    $this->actingAs(User::factory()->create())->patchJson(route('files.change-type', $file), ['file_type' => 'invoice'])->assertNotFound();
    $this->actingAs($owner)->patchJson(route('files.change-type', $file), ['file_type' => 'unsupported'])->assertUnprocessable();
    $this->patchJson(route('files.change-type', $file), ['file_type' => 'invoice'])->assertUnprocessable();
    expect($file->fresh()->status)->toBe('needs_review');
    Queue::assertNothingPushed();
});

it('rejects out-of-range or unsupported classification decisions', function (string $type, float $confidence) {
    expect((new ClassificationResult($type, $confidence, 'Decision'))->isValid())->toBeFalse();
})->with([['receipt', 1.1], ['unsupported', 0.9], ['unknown', 1.0]]);

it('uses the corrected type for extraction without classifying the document again', function () {
    config(['ai.providers.gemini.api_key' => 'test']);
    Http::fake(['*' => Http::response([
        'totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"conditions":"Unopened items accepted"}']]]]],
    ])]);
    $path = tempnam(sys_get_temp_dir(), 'corrected_').'.txt';
    file_put_contents($path, 'Return unopened items');
    $file = File::factory()->create(['fileExtension' => 'txt', 'fileType' => 'text/plain', 'meta' => ['review' => ['corrected_type' => 'return_policy']]]);
    $this->mock(WorkerFileManager::class, function ($mock) use ($path): void {
        $mock->shouldReceive('processWithCleanup')->once()->andReturnUsing(fn ($s3, $guid, $extension, $callback) => $callback($path));
    });
    $this->mock(GeminiFileManager::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')->once()->andReturn(['fileUri' => 'https://test/file', 'name' => 'files/test', 'mimeType' => 'text/plain']);
        $mock->shouldReceive('waitUntilActive')->once();
        $mock->shouldReceive('deleteFile')->once()->andReturnTrue();
    });
    $this->mock(GeminiTypeClassifier::class, fn ($mock) => $mock->shouldNotReceive('classify'));
    $this->mock(FilePreviewManager::class, fn ($mock) => $mock->shouldReceive('generatePreviewForFile')->once()->andReturnFalse());
    $job = new class('corrected-test', ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileExtension' => 'txt', 's3OriginalPath' => 'source.txt']) extends ProcessFileGemini
    {
        public function __construct(string $id, private array $metadata)
        {
            parent::__construct($id);
        }

        protected function getMetadata(): ?array
        {
            return $this->metadata;
        }

        protected function updateProgress(int $progress): void {}

        protected function storeMetadata(array $metadata): void {}

        public function process(): void
        {
            $this->handleJob();
        }
    };
    try {
        $job->process();
        expect($file->fresh()->status)->toBe('completed')->and($file->extractableEntities()->count())->toBe(1)
            ->and(ReturnPolicy::firstOrFail()->conditions)->toBe('Unopened items accepted');
    } finally {
        unlink($path);
    }
});

it('resolves reconciled receipt review without rerunning extraction and updates all views', function (bool $encoded): void {
    $this->withoutVite();
    Queue::fake();
    $file = File::factory()->create(['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'receipt_totals']]]);
    $receipt = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id,
        'total_amount' => '21.56', 'tax_amount' => 0, 'receipt_data' => $encoded ? json_encode(['totals' => ['total_discount' => '1.14']]) : ['totals' => ['total_discount' => '1.14']]]);
    $receipt->lineItems()->createMany([
        ['text' => 'Wine', 'qty' => 1, 'price' => '14.80', 'total' => '14.80'],
        ['text' => 'Wine', 'qty' => 1, 'price' => '7.90', 'total' => '7.90'],
    ]);
    $this->actingAs($file->user)->get(route('files.extraction-report', $file))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.needs_review', false)
            ->where('report.reconciliation.discount_amount', '1.14'));
    $this->post(route('files.resolve-review', $file), ['confirmed' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->status)->toBe('completed')->and($file->fresh()->meta['review_resolution']['reviewed_by'])->toBe($file->user_id)
        ->and($file->fresh()->meta)->not->toHaveKey('review');
    $this->get(route('files.show', $file))->assertInertia(fn (Assert $page) => $page->where('file.status', 'completed'));
    $this->get(route('files.index', ['file_id' => $file->id]))->assertInertia(fn (Assert $page) => $page->where('files.data.0.status', 'completed')->where('stats.needs_review', 0));
    $this->get(route('library.index'))->assertInertia(fn (Assert $page) => $page->where('files.data.0.status', 'completed'));
    $this->get(route('receipts.show', $receipt))->assertInertia(fn (Assert $page) => $page->where('receipt.file.needs_review', false));
    Queue::assertNotPushed(ProcessFileGemini::class);
})->with([false, true]);

it('rejects blind inconsistent foreign and non-totals review confirmations', function (string $scenario): void {
    Queue::fake();
    $file = File::factory()->create(['status' => 'needs_review', 'meta' => ['review' => ['reason' => $scenario === 'limit' ? 'processing_limit' : 'receipt_totals']]]);
    $receipt = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id, 'tax_amount' => 0,
        'total_amount' => $scenario === 'inconsistent' ? '40.00' : '10.00']);
    if ($scenario !== 'no items') {
        $receipt->lineItems()->create(['text' => 'Item', 'qty' => 1, 'price' => 10, 'total' => 10]);
    }
    $user = $scenario === 'foreign' ? User::factory()->create() : $file->user;
    $response = $this->actingAs($user)->postJson(route('files.resolve-review', $file), ['confirmed' => $scenario !== 'blind']);
    if ($scenario === 'foreign') {
        $response->assertNotFound();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors($scenario === 'blind' ? 'confirmed' : 'review');
    }
    expect($file->fresh()->status)->toBe('needs_review');
    Queue::assertNotPushed(ProcessFileGemini::class);
})->with(['blind', 'inconsistent', 'foreign', 'limit', 'no items']);

it('retries extraction separately when receipt source values are missing', function (): void {
    $file = File::factory()->create(['status' => 'needs_review', 'meta' => ['review' => ['reason' => 'receipt_totals']]]);
    $this->mock(FileReprocessingService::class)->shouldReceive('reprocessFile')->once()
        ->withArgs(fn (File $selected, bool $force, ?string $provider, bool $fresh): bool => $selected->id === $file->id && ! $force && $provider === null && $fresh)
        ->andReturn(['success' => true, 'message' => 'Queued', 'jobId' => 'retry']);
    $this->actingAs($file->user)->post(route('files.reprocess', $file))->assertRedirect()->assertSessionHas('success', 'Queued');
});
