<?php

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Jobs\BaseJob;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\BulkUpload\BulkUploadService;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->session = BulkUploadSession::factory()->create(['user_id' => $this->user->id, 'total_files' => 1]);
    $this->file = File::factory()->create(['user_id' => $this->user->id, 'status' => 'processing']);
    $this->jobId = (string) Str::uuid();
    JobMetadataPersistence::store($this->jobId, ['fileId' => $this->file->id, 'userId' => $this->user->id]);
    $this->bulkFile = BulkUploadFile::factory()->create(['bulk_upload_session_id' => $this->session->id, 'user_id' => $this->user->id,
        'file_id' => $this->file->id, 'job_id' => $this->jobId, 'status' => BulkUploadFileStatus::Processing]);
});

it('advances bulk file and session from a successful final processing step', function (): void {
    $this->file->update(['status' => 'completed']);
    $job = new class($this->jobId) extends BaseJob
    {
        protected function handleJob(): void {}
    };
    $job->handle();
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Completed)
        ->and($this->session->refresh()->status)->toBe(BulkUploadSessionStatus::Completed)
        ->and($this->session->completed_count)->toBe(1);
});

it('exposes terminal failures without losing accepted confirmation identifiers', function (): void {
    $job = new class($this->jobId) extends BaseJob
    {
        protected function handleJob(): void {}
    };
    $job->failed(new RuntimeException('Extraction rejected invalid data'));
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Failed)
        ->and($this->bulkFile->error_message)->toBe('Extraction rejected invalid data')
        ->and($this->bulkFile->job_id)->toBe($this->jobId)
        ->and($this->session->refresh()->failed_count)->toBe(1)
        ->and($this->session->uploaded_count)->toBe(1);
});

it('recovers missed result hooks using durable children on status reads and scheduled reconciliation', function (): void {
    $this->file->update(['status' => 'needs_review']);
    JobHistory::create(['uuid' => (string) Str::uuid(), 'parent_uuid' => $this->jobId, 'name' => 'Extraction', 'status' => 'completed', 'queue' => 'documents']);
    $this->artisan('bulk:reconcile')->assertSuccessful();
    $status = app(BulkUploadService::class)->getSessionStatus($this->session);
    expect($status['summary']['completed_count'])->toBe(1)->and($status['summary']['is_complete'])->toBeTrue();
});

it('counts skipped files while preserving cancellation and a stable completion time', function (): void {
    $this->session->update(['total_files' => 2, 'status' => BulkUploadSessionStatus::Cancelled]);
    BulkUploadFile::factory()->create(['user_id' => $this->user->id, 'bulk_upload_session_id' => $this->session->id, 'status' => BulkUploadFileStatus::Skipped]);
    $this->file->update(['status' => 'completed']);
    JobHistory::where('uuid', $this->jobId)->update(['status' => 'completed']);
    app(BulkUploadService::class)->reconcileSession($this->session);
    $completed = $this->session->refresh()->completed_at->toISOString();
    $this->travel(1)->minutes();
    $this->session->checkCompletion();
    expect($this->session->status)->toBe(BulkUploadSessionStatus::Cancelled)->and($this->session->completed_at->toISOString())->toBe($completed);
});
