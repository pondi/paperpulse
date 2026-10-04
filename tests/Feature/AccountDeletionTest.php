<?php

use App\Jobs\BaseJob;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\Document;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Files\FileCleanupService;
use App\Services\Files\FileDeletionService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Storage::fake('uplink');
    Storage::fake('local');
});

test('account deletion captures owned binaries and search records before cascading and revokes access', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 's3_original_path' => 'documents/'.$owner->id.'/source.pdf']);
    $foreign = File::factory()->create(['user_id' => $other->id, 's3_original_path' => 'documents/'.$other->id.'/source.pdf']);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $item = $receipt->lineItems()->create(['text' => 'Item', 'qty' => 1, 'price' => 10, 'total' => 10]);
    $document = Document::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $document->delete();
    $owner->createToken('client');
    $owner->getConnection()->table('sessions')->insert([
        'id' => 'old-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $source = PulseDavFile::create([
        'user_id' => $owner->id, 's3_path' => 'incoming/'.$owner->id.'/scan.pdf',
        'filename' => 'scan.pdf', 'uploaded_at' => now(),
    ]);
    Storage::disk('paperpulse')->put($file->s3_original_path, 'source');
    Storage::disk('paperpulse')->put($foreign->s3_original_path, 'foreign');
    Storage::disk('pulsedav')->put($source->s3_path, 'incoming');
    Storage::disk('pulsedav')->put('archive/'.$source->s3_path, 'archive');
    Storage::disk('local')->put('private/exports/'.$owner->id.'/1/archive.pdf', 'owned export');
    Storage::disk('local')->put('private/exports/'.$other->id.'/2/archive.pdf', 'foreign export');

    $owner->delete();

    expect($owner->fresh())->toBeNull()->and(File::withTrashed()->find($file->id))->toBeNull();
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $owner->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $owner->id]);
    Storage::disk('local')->assertMissing('private/exports/'.$owner->id.'/1/archive.pdf');
    Storage::disk('local')->assertExists('private/exports/'.$other->id.'/2/archive.pdf');
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();
    expect($manifest->search_records)->toContain(
        ['type' => Receipt::class, 'id' => $receipt->id, 'done' => false],
        ['type' => $item::class, 'id' => $item->id, 'done' => false],
        ['type' => Document::class, 'id' => $document->id, 'done' => false],
    );
    Storage::disk('paperpulse')->assertExists($file->s3_original_path);
    $this->travel(1)->seconds();
    FileCleanupManifest::where('user_id', $owner->id)->each(function ($manifest): void {
        expect(app(FileCleanupService::class)->process($manifest)['failed'])->toBe(0);
        expect($manifest->fresh()->completed_at)->not->toBeNull();
    });
    Storage::disk('paperpulse')->assertMissing($file->s3_original_path);
    Storage::disk('pulsedav')->assertMissing([$source->s3_path, 'archive/'.$source->s3_path]);
    Storage::disk('paperpulse')->assertExists($foreign->s3_original_path);
    expect($foreign->fresh())->not->toBeNull();
});

test('account cleanup survives a storage outage and resumes from the durable manifest', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $owner->delete();
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();
    $this->travel(1)->seconds();
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->andReturn(false);
    expect((new FileCleanupService($storage))->process($manifest)['failed'])->toBeGreaterThan(0);
    expect($manifest->fresh()->completed_at)->toBeNull();
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->andReturn(true);
    expect((new FileCleanupService($storage))->process($manifest)['failed'])->toBe(0);
    expect($manifest->fresh()->completed_at)->not->toBeNull();
});

test('late processing and failure callbacks cannot revive a deleted account chain', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $job = new class((string) Str::uuid()) extends BaseJob
    {
        protected function handleJob(): void
        {
            throw new RuntimeException('Deleted account work must not execute.');
        }
    };
    JobMetadataPersistence::store($job->jobID, ['fileId' => $file->id]);
    $owner->delete();
    $job->handle();
    $job->failed(new RuntimeException('Late failure'));

    expect(JobHistory::where('uuid', $job->jobID)->first()->status)->toBe('cancelled');
    expect(JobHistory::where('uuid', $job->uuid)->exists())->toBeFalse();
    expect(File::withTrashed()->where('user_id', $owner->id)->exists())->toBeFalse();
});

test('manifest capture failure rolls back account deletion and access revocation', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $token = $owner->createToken('client')->accessToken;
    $this->mock(FileDeletionService::class)->shouldReceive('deleteFile')->once()->andThrow(new RuntimeException('Capture failed'));

    expect(fn () => $owner->delete())->toThrow(RuntimeException::class, 'Capture failed');
    expect($owner->fresh())->not->toBeNull()->and($file->fresh()->trashed())->toBeFalse();
    expect($token->fresh())->not->toBeNull()->and(FileCleanupManifest::count())->toBe(0);
});

test('account cleanup waits out upload grants and removes a late incoming object', function (): void {
    $owner = User::factory()->create();
    $session = BulkUploadSession::factory()->create(['user_id' => $owner->id]);
    $upload = BulkUploadFile::factory()->presigned()->create([
        'user_id' => $owner->id, 'bulk_upload_session_id' => $session->id,
        's3_key' => 'bulk/'.$owner->id.'/late.pdf',
    ]);
    $owner->delete();
    $manifest = FileCleanupManifest::whereNull('file_id')->where('user_id', $owner->id)->firstOrFail();
    app(FileCleanupService::class)->process($manifest);
    expect($manifest->fresh()->completed_at)->toBeNull();
    Storage::disk('uplink')->put($upload->s3_key, 'late PUT');
    $this->travel(32)->minutes();
    app(FileCleanupService::class)->process($manifest);
    Storage::disk('uplink')->assertMissing($upload->s3_key);
    expect($manifest->fresh()->completed_at)->not->toBeNull();
});

test('search cleanup retries after the account and entity identities have been removed', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $owner->delete();
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteFile')->andReturn(true);
    $service = Mockery::mock(FileCleanupService::class, [$storage])->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('removeSearchRecord')->once()->andThrow(new RuntimeException('Index offline'));
    expect($service->process($manifest)['failed'])->toBe(1);
    expect($manifest->fresh()->search_records[0]['done'])->toBeFalse();
    (new FileCleanupService($storage))->process($manifest);
    expect($manifest->fresh()->search_records[0])->toBe(['type' => Document::class, 'id' => $document->id, 'done' => true]);
});

test('account cleanup preserves an original referenced by a surviving file', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 's3_original_path' => 'documents/'.$owner->id.'/shared/original.pdf']);
    $survivor = File::factory()->create(['s3_original_path' => $file->s3_original_path]);
    Storage::disk('paperpulse')->put($file->s3_original_path, 'shared original');

    $owner->delete();
    $this->travel(1)->seconds();
    $manifest = FileCleanupManifest::where('file_id', $file->id)->firstOrFail();

    expect(app(FileCleanupService::class)->process($manifest)['failed'])->toBe(0)
        ->and($manifest->fresh()->completed_at)->not->toBeNull()
        ->and($survivor->fresh())->not->toBeNull();
    Storage::disk('paperpulse')->assertExists($file->s3_original_path);
});
