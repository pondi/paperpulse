<?php

use App\Events\FileExtractionCompleted;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\DocumentProcessed;
use App\Notifications\ReceiptProcessed;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use App\Services\DuplicateDetectionService;
use App\Services\EntityFactory;
use App\Services\Files\FilePreviewManager;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['queue.connections.database.after_commit' => true]);
    Event::fake([FileExtractionCompleted::class]);
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create([
        'user_id' => $this->owner->id,
        'processing_type' => 'gemini',
        's3_original_path' => 'source.pdf',
    ]);
    $this->needsReview = false;
    $this->primaryType = 'receipt';
    $this->realNotifications = app(ChannelManager::class);
    $this->jobId = (string) Str::uuid();
    JobMetadataPersistence::store($this->jobId, ['fileId' => $this->file->id, 'fileExtension' => $this->file->fileExtension]);
    $this->job = new ProcessFileGemini($this->jobId);

    $this->mock(WorkerFileManager::class, fn ($mock) => $mock->shouldReceive('processWithCleanup')->andReturn([
        'typeInfo' => ['type' => 'receipt'],
        'parsed' => ['entities' => [], 'provider_response' => []],
    ]));
    $this->mock(GeminiFileManager::class);
    $this->mock(GeminiTypeClassifier::class);
    $this->mock(FilePreviewManager::class);
    $this->mock(EntityFactory::class, function ($mock): void {
        $mock->shouldReceive('createEntitiesFromParsedData')->andReturnUsing(function (array $parsed, File $file): array {
            if ($this->primaryType === 'document') {
                $document = Document::factory()->create(['file_id' => $file->id, 'user_id' => $file->user_id]);
                ExtractableEntity::create(['file_id' => $file->id, 'user_id' => $file->user_id,
                    'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
                if ($this->needsReview) {
                    $file->update(['status' => 'needs_review']);
                }

                return [['type' => 'document', 'model' => $document]];
            }
            $receipt = Receipt::factory()->create([
                'file_id' => $file->id, 'user_id' => $file->user_id, 'total_amount' => 42, 'currency' => 'NOK',
            ]);
            ExtractableEntity::create([
                'file_id' => $file->id, 'user_id' => $file->user_id,
                'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true, 'extracted_at' => now(),
            ]);
            if ($this->needsReview) {
                $file->update(['status' => 'needs_review']);
            }

            return [['type' => 'receipt', 'model' => $receipt]];
        });
    });
    $this->mock(DuplicateDetectionService::class, fn ($mock) => $mock->shouldReceive('flagReceiptDuplicates'));
});

it('queues completion once for the selected channels despite repeated Gemini deliveries', function (bool $inApp, bool $email, array $channels): void {
    $this->owner->preferences()->create([
        'notify_processing_complete' => $inApp, 'email_notify_processing_complete' => $email,
    ]);
    $this->job->handle();
    $this->job->handle();
    (new ProcessFileGemini($this->jobId))->handle();

    expect($this->file->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('receipts', 1);
    $this->assertDatabaseCount('jobs', count($channels));
    $queue = Queue::connection('database');
    foreach ($channels as $channel) {
        $queuedJob = $queue->pop();
        $command = unserialize($queuedJob->payload()['data']['command']);
        expect($command)->toBeInstanceOf(SendQueuedNotifications::class)
            ->and($command->channels)->toBe([$channel])
            ->and($command->notification)->toBeInstanceOf(ReceiptProcessed::class)
            ->and($command->notifiables->first()->id)->toBe($this->owner->id)
            ->and($command->notification->toArray($this->owner)['receipt_id'])->toBe(Receipt::firstOrFail()->id);
        $queuedJob->delete();
    }
})->with([
    'in app' => [true, false, ['database', 'broadcast']],
    'email' => [false, true, ['mail']],
    'both' => [true, true, ['database', 'broadcast', 'mail']],
    'disabled' => [false, false, []],
]);

it('delivers Gemini completion through the database queue worker', function (): void {
    $this->job->handle();
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('jobs', 2);

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();

    $notification = $this->owner->notifications()->sole();
    expect($notification->type)->toBe(ReceiptProcessed::class)
        ->and($notification->data['receipt_id'])->toBe(Receipt::firstOrFail()->id)
        ->and($notification->data['amount'])->toBe('42.00')
        ->and($notification->data['currency'])->toBe('NOK');
});

it('retries a failed notification handoff without losing completion or duplicating it', function (): void {
    Notification::shouldReceive('send')->once()->andReturnUsing(function ($notifiables, $notification): void {
        $this->realNotifications->send($notifiables, $notification);
        throw new RuntimeException('Queue offline');
    });
    expect(fn () => $this->job->handle())->toThrow(RuntimeException::class, 'Queue offline');
    expect($this->file->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('receipts', 0);
    $this->assertDatabaseCount('jobs', 0);

    Notification::swap($this->realNotifications);
    $this->job->handle();
    $this->job->handle();
    expect($this->file->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('receipts', 1);
    $this->assertDatabaseCount('jobs', 2);
});

it('does not send successful completion for receipts awaiting review', function (): void {
    $this->needsReview = true;
    $this->job->handle();
    expect($this->file->fresh()->status)->toBe('needs_review');
    $this->assertDatabaseCount('jobs', 0);
});

it('queues document completion once and respects in-app and email preferences', function (bool $inApp, bool $email, array $channels): void {
    $this->primaryType = 'document';
    $this->owner->preferences()->create(['notify_processing_complete' => $inApp, 'email_notify_processing_complete' => $email]);
    $this->job->handle();
    $this->job->handle();
    expect($this->file->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('documents', 1);
    $this->assertDatabaseCount('jobs', count($channels));
    $queue = Queue::connection('database');
    foreach ($channels as $channel) {
        $queuedJob = $queue->pop();
        $command = unserialize($queuedJob->payload()['data']['command']);
        expect($command->channels)->toBe([$channel])->and($command->notification)->toBeInstanceOf(DocumentProcessed::class)
            ->and($command->notification->toArray($this->owner)['file_id'])->toBe($this->file->id);
        $queuedJob->delete();
    }
})->with([
    [true, false, ['database', 'broadcast']], [false, true, ['mail']],
    [true, true, ['database', 'broadcast', 'mail']], [false, false, []],
]);

it('does not notify successful document completion while extraction needs review', function (): void {
    $this->primaryType = 'document';
    $this->needsReview = true;
    $this->job->handle();
    expect($this->file->fresh()->status)->toBe('needs_review');
    $this->assertDatabaseCount('jobs', 0);
});
