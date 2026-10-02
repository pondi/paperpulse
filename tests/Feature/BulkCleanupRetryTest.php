<?php

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->disk = Storage::fake('uplink');
    Storage::fake('paperpulse');
    $this->user = User::factory()->create();
    $this->session = BulkUploadSession::factory()->create(['user_id' => $this->user->id, 'status' => BulkUploadSessionStatus::Cancelled, 'total_files' => 1]);
    $this->prefix = 'uplink-incoming/'.$this->user->id.'/'.$this->session->uuid.'/';
    $this->bulkFile = BulkUploadFile::factory()->presigned()->create(['bulk_upload_session_id' => $this->session->id, 'user_id' => $this->user->id, 's3_key' => $this->prefix.'source.pdf']);
});

it('sweeps late PUTs only after all upload grants expire', function (): void {
    $this->disk->put($this->bulkFile->s3_key, 'late upload');
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    expect($this->session->refresh()->cleanup_pending)->toBeTrue();
    $this->disk->assertExists($this->bulkFile->s3_key);
    $this->travel(31)->minutes();
    $this->disk->put($this->prefix.'another-late-file.png', 'late upload');
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    expect($this->session->refresh()->cleanup_pending)->toBeFalse();
    $this->disk->assertMissing($this->bulkFile->s3_key);
    $this->disk->assertMissing($this->prefix.'another-late-file.png');
});

it('retries deletion failures independently of the cancelled session status', function (): void {
    $this->bulkFile->update(['presigned_expires_at' => now()->subMinute()]);
    $this->disk->put($this->bulkFile->s3_key, 'orphan');
    $failed = Mockery::mock($this->disk)->makePartial();
    $failed->shouldReceive('delete')->once()->andReturn(false);
    Storage::set('uplink', $failed);
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    expect($this->session->refresh()->cleanup_pending)->toBeTrue()->and($this->session->status)->toBe(BulkUploadSessionStatus::Cancelled);
    Storage::set('uplink', $this->disk);
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    expect($this->session->refresh()->cleanup_pending)->toBeFalse();
    $this->disk->assertMissing($this->bulkFile->s3_key);
});

it('protects pending handoffs and active original assets while cleaning handed-off sources', function (): void {
    $file = File::factory()->create(['user_id' => $this->user->id, 'status' => 'processing', 's3_original_path' => 'receipts/original.pdf']);
    $request = FileProcessingRequest::create(['job_id' => 'pending-handoff', 'user_id' => $this->user->id, 'file_id' => $file->id,
        'file_type' => 'receipt', 'guid' => $file->guid, 'extension' => 'pdf', 'storage_disk' => 'paperpulse', 'original_path' => $file->s3_original_path, 'state' => 'upload_pending']);
    $this->bulkFile->update(['file_id' => $file->id, 'job_id' => $request->job_id, 'status' => BulkUploadFileStatus::Processing, 'presigned_expires_at' => now()->subMinute()]);
    $this->disk->put($this->bulkFile->s3_key, 'source');
    Storage::disk('paperpulse')->put($file->s3_original_path, 'active original');
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    $this->disk->assertExists($this->bulkFile->s3_key);
    expect($this->session->refresh()->cleanup_pending)->toBeTrue();
    $request->update(['state' => 'dispatched']);
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    $this->disk->assertMissing($this->bulkFile->s3_key);
    Storage::disk('paperpulse')->assertExists($file->s3_original_path);
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Processing);
});

it('cleans expired sessions without touching a neighbouring prefix and honors dry run', function (): void {
    $this->session->update(['status' => BulkUploadSessionStatus::Uploading, 'expires_at' => now()->subDays(3)]);
    $this->bulkFile->update(['presigned_expires_at' => now()->subDay()]);
    $this->disk->put($this->bulkFile->s3_key, 'expired');
    $this->disk->put(rtrim($this->prefix, '/').'-other/source.pdf', 'keep');
    $this->artisan('bulk:cleanup-expired --dry-run')->assertSuccessful();
    $this->disk->assertExists($this->bulkFile->s3_key);
    $this->artisan('bulk:cleanup-expired')->assertSuccessful();
    $this->disk->assertMissing($this->bulkFile->s3_key);
    $this->disk->assertExists(rtrim($this->prefix, '/').'-other/source.pdf');
    expect($this->bulkFile->refresh()->status)->toBe(BulkUploadFileStatus::Skipped);
});
