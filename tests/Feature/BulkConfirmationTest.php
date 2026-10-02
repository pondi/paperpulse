<?php

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\User;
use App\Services\BulkUpload\BulkUploadService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Bus::fake();
    Storage::fake('uplink');
    Storage::fake('paperpulse');
    $this->user = User::factory()->create();
    $this->session = BulkUploadSession::factory()->create(['user_id' => $this->user->id, 'total_files' => 1]);
    $this->bytes = conversionPdfFixture();
    $this->bulkFile = BulkUploadFile::factory()->presigned()->create([
        'user_id' => $this->user->id, 'bulk_upload_session_id' => $this->session->id,
        's3_key' => 'uplink-incoming/'.$this->user->id.'/'.$this->session->uuid.'/receipt.pdf',
        'original_filename' => 'receipt.pdf', 'file_extension' => 'pdf', 'mime_type' => 'application/pdf',
        'file_hash' => hash('sha256', $this->bytes), 'file_size' => strlen($this->bytes),
    ]);
    Storage::disk('uplink')->put($this->bulkFile->s3_key, $this->bytes);
});

it('confirms once and replays stale deliveries after session termination', function (): void {
    $service = app(BulkUploadService::class);
    $first = $service->confirmFile($this->session, $this->bulkFile);
    $this->session->update(['status' => BulkUploadSessionStatus::Completed, 'expires_at' => now()->subHour()]);
    expect($service->confirmFile($this->session, $this->bulkFile))->toBe($first)
        ->and(File::withoutGlobalScope('user')->count())->toBe(1)
        ->and(FileProcessingRequest::count())->toBe(1);
    expect($this->bulkFile->refresh()->job_id)->toBe($first['job_id']);
});

it('rejects oversized objects before reading their bytes', function (): void {
    $disk = Mockery::mock(Filesystem::class);
    Storage::shouldReceive('disk')->with('uplink')->andReturn($disk);
    $disk->shouldReceive('size')->once()->andReturn(101 * 1024 * 1024);
    $disk->shouldNotReceive('readStream');
    expect(fn () => app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile))->toThrow(Exception::class, 'processing limit');
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Failed);
    expect(FileProcessingRequest::count())->toBe(0);
});

it('rejects checksum and byte spoofing without a handoff and allows a corrected retry', function (): void {
    Storage::disk('uplink')->put($this->bulkFile->s3_key, str_repeat('x', strlen($this->bytes)));
    expect(fn () => app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile))->toThrow(Exception::class, 'checksum');
    Storage::disk('uplink')->put($this->bulkFile->s3_key, $this->bytes);
    $result = app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile);
    expect($this->bulkFile->refresh()->file_id)->toBe($result['file_id']);
});

it('uses locked current state when cancellation precedes a stale confirmation or presign', function (): void {
    app(BulkUploadService::class)->cancelSession($this->session);
    expect(fn () => app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile))->toThrow(Exception::class, 'not active')
        ->and(fn () => app(BulkUploadService::class)->presignSingleFile($this->session, $this->bulkFile))->toThrow(Exception::class, 'not active');
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Skipped);
});

it('keeps accepted work and prevents re-presigning after a processing failure', function (): void {
    $result = app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile);
    $this->bulkFile->refresh()->update(['status' => BulkUploadFileStatus::Failed]);
    expect(app(BulkUploadService::class)->confirmFile($this->session, $this->bulkFile))->toBe($result);
    expect(fn () => app(BulkUploadService::class)->presignSingleFile($this->session, $this->bulkFile))->toThrow(Exception::class, 'cannot be presigned');
    app(BulkUploadService::class)->cancelSession($this->session);
    expect($this->bulkFile->refresh()->file_id)->toBe($result['file_id'])->and($this->bulkFile->status)->toBe(BulkUploadFileStatus::Failed);
});
