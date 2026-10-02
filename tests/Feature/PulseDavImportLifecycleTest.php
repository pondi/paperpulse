<?php

use App\Jobs\PulseDav\ProcessPulseDavFile;
use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Notifications\ScannerFilesImported;
use App\Services\FileProcessingService;
use App\Services\PulseDav\ImportService;
use App\Services\PulseDav\PulseDavImportService;
use App\Services\PulseDav\ScannerImportNotifier;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Bus::fake();
    Notification::fake();
    Storage::fake('pulsedav');
    config(['services.pulsedav.s3_incoming_prefix' => 'incoming/']);
    $this->user = User::factory()->create();
    $this->user->preferences()->create(['notify_scanner_import' => true]);
    $this->batch = PulseDavImportBatch::create(['user_id' => $this->user->id, 'file_count' => 0]);
    $path = 'incoming/'.$this->user->id.'/source.pdf';
    Storage::disk('pulsedav')->put($path, conversionPdfFixture());
    $this->source = PulseDavFile::create(['user_id' => $this->user->id, 's3_path' => $path, 'filename' => 'source.pdf', 'status' => 'pending', 'uploaded_at' => now()]);
});

it('claims a source once across batches and stale service deliveries', function (): void {
    $otherBatch = PulseDavImportBatch::create(['user_id' => $this->user->id, 'file_count' => 0]);
    expect(ImportService::importFile($this->source, $this->batch, 'document'))->toBeTrue()
        ->and(ImportService::importFile($this->source, $otherBatch, 'document'))->toBeFalse();
    expect($this->source->refresh()->status)->toBe('queued')->and($this->batch->refresh()->file_count)->toBe(1);
    $duplicateSource = PulseDavFile::create(['user_id' => $this->user->id, 's3_path' => $this->source->s3_path, 'filename' => 'source.pdf', 'status' => 'pending', 'uploaded_at' => now()]);
    expect(ImportService::importFile($duplicateSource, $otherBatch, 'document'))->toBeFalse();
    Bus::assertDispatched(ProcessPulseDavFile::class, 1);
    Bus::assertDispatched(ProcessPulseDavFile::class, fn ($job) => $job->jobID === $this->source->job_id && $job->connection === 'database');
});

it('keeps handoff distinct from successful extraction and does not hand off twice', function (): void {
    ImportService::importFile($this->source, $this->batch, 'document');
    $job = Bus::dispatched(ProcessPulseDavFile::class)->first();
    $file = File::factory()->create(['user_id' => $this->user->id, 'status' => 'pending']);
    $this->mock(FileProcessingService::class)->shouldReceive('processPulseDavFile')->once()->andReturn(['fileId' => $file->id]);
    $job->handle();
    $job->handle();
    expect($this->source->refresh()->status)->toBe('handed_off')->and($this->source->file_id)->toBe($file->id)
        ->and($this->source->processed_at)->toBeNull();
    Notification::assertNothingSent();
    expect(app(PulseDavImportService::class)->getBatchStats($this->batch)['processing_files'])->toBe(1);
});

it('derives completion and notifications for every extracted entity type', function (string $entityClass): void {
    ImportService::importFile($this->source, $this->batch, 'document');
    $file = File::factory()->create(['user_id' => $this->user->id, 'status' => 'completed']);
    $entity = $entityClass::factory()->create(['user_id' => $this->user->id, 'file_id' => $file->id]);
    if (! $file->extractableEntities()->exists()) {
        ExtractableEntity::factory()->create(['user_id' => $this->user->id, 'file_id' => $file->id, 'entity_type' => $entityClass, 'entity_id' => $entity->id, 'is_primary' => true]);
    }
    $this->source->refresh()->update(['status' => 'handed_off', 'file_id' => $file->id]);
    ImportService::reconcileFile($this->source);
    ImportService::reconcileFile($this->source);
    expect($this->source->refresh()->status)->toBe('completed')->and($this->batch->refresh()->completed_count)->toBe(1);
    Notification::assertSentTo($this->user, ScannerFilesImported::class, fn ($notification) => $notification->toArray($this->user)['processed_count'] === 1);
    Notification::assertSentTimes(ScannerFilesImported::class, 1);
})->with([Receipt::class, Document::class, Invoice::class, Contract::class, Voucher::class, Warranty::class, ReturnPolicy::class, BankStatement::class]);

it('waits for all batch files before notifying with successful and failed terminal counts', function (): void {
    ImportService::importFile($this->source, $this->batch, 'document');
    $this->batch->update(['file_count' => 2]);
    $file = File::factory()->create(['user_id' => $this->user->id, 'status' => 'failed']);
    $this->source->refresh()->update(['file_id' => $file->id, 'status' => 'handed_off']);
    ImportService::reconcileFile($this->source);
    Notification::assertNothingSent();
    PulseDavFile::create(['user_id' => $this->user->id, 's3_path' => 'incoming/'.$this->user->id.'/other.pdf', 'filename' => 'other.pdf', 'uploaded_at' => now(), 'import_batch_id' => $this->batch->id, 'status' => 'completed']);
    ScannerImportNotifier::notifyBatch($this->batch->id);
    $stats = app(PulseDavImportService::class)->getBatchStats($this->batch);
    expect($stats['completed_files'])->toBe(1)->and($stats['failed_files'])->toBe(1);
    Notification::assertSentTo($this->user, ScannerFilesImported::class, fn ($notification) => $notification->toArray($this->user)['failed_count'] === 1 && $notification->toArray($this->user)['processed_count'] === 1);
});

it('reclaims only expired claims with no durable queue delivery or recent execution', function (): void {
    ImportService::importFile($this->source, $this->batch, 'document');
    $jobId = $this->source->fresh()->job_id;
    $queueId = $this->source->getConnection()->table('jobs')->insertGetId(['queue' => 'default', 'payload' => $jobId, 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
    $this->travel(2)->hours();
    expect(ImportService::importFile($this->source, $this->batch, 'document'))->toBeFalse();
    $this->source->getConnection()->table('jobs')->where('id', $queueId)->delete();
    expect(ImportService::importFile($this->source, $this->batch, 'document'))->toBeTrue();
    expect($this->source->refresh()->job_id)->not->toBe($jobId);
    Bus::assertDispatched(ProcessPulseDavFile::class, 2);
});

it('reconciles worker failures without waiting for a success status-update step', function (): void {
    ImportService::importFile($this->source, $this->batch, 'document');
    JobHistory::where('uuid', $this->source->fresh()->job_id)->update(['status' => 'failed', 'exception' => 'Terminal extraction failure']);
    $this->artisan('pulsedav:reconcile-imports')->assertSuccessful();
    expect($this->source->refresh()->status)->toBe('failed')->and($this->batch->refresh()->failed_count)->toBe(1);
});
