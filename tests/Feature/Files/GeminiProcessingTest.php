<?php

use App\Exceptions\GeminiApiException;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\User;
use App\Services\Files\StoragePathBuilder;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('processes a receipt image with gemini and stores metadata', function () {
    $user = User::factory()->create();
    $guid = 'receipt-fixture-'.uniqid();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'receipt.png',
        'fileExtension' => 'png',
        'fileType' => 'image/png',
        'file_type' => 'receipt',
        'status' => 'pending',
    ]);

    $path = StoragePathBuilder::storagePath($user->id, $guid, 'receipt', 'original', 'png');
    $pngPath = createFixturePngPath();
    Storage::disk('paperpulse')->put($path, file_get_contents($pngPath));
    $file->update(['s3_original_path' => $path]);

    $jobId = 'job-receipt-'.uniqid();
    storeGeminiJobMetadata($jobId, $file, $path);

    (new ProcessFileGemini($jobId))->handle();

    $file->refresh();

    expect($file->status)->toBe('completed');
    expect($file->processing_type)->toBe('gemini');
    expect($file->meta['gemini']['provider_response']['provider'] ?? null)->toBe('gemini');
    expect($file->meta['gemini']['entities'] ?? null)->toBeArray();
});

it('records document subtype for text files', function () {
    $user = User::factory()->create();
    $guid = 'text-fixture-'.uniqid();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'notes.txt',
        'fileExtension' => 'txt',
        'fileType' => 'text/plain',
        'file_type' => 'document',
        'status' => 'pending',
    ]);

    $path = StoragePathBuilder::storagePath($user->id, $guid, 'document', 'original', 'txt');
    Storage::disk('paperpulse')->put($path, 'Plain text content for Gemini.');
    $file->update(['s3_original_path' => $path]);

    $jobId = 'job-text-'.uniqid();
    storeGeminiJobMetadata($jobId, $file, $path);

    (new ProcessFileGemini($jobId))->handle();

    $file->refresh();

    expect($file->meta['gemini']['type'] ?? null)->toBe('document');
    expect($file->meta['gemini']['subtype'] ?? null)->toBe('text');
});

it('marks the file as failed on gemini validation errors', function () {
    config(['ai.providers.gemini.max_file_size_mb' => 1]);

    $user = User::factory()->create();
    $guid = 'oversize-fixture-'.uniqid();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'oversize.pdf',
        'fileExtension' => 'pdf',
        'fileType' => 'application/pdf',
        'file_type' => 'document',
        'status' => 'pending',
    ]);

    $path = StoragePathBuilder::storagePath($user->id, $guid, 'document', 'original', 'pdf');
    $oversizeContent = str_repeat('A', 1024 * 1024 + 10);
    Storage::disk('paperpulse')->put($path, $oversizeContent);
    $file->update(['s3_original_path' => $path]);

    $jobId = 'job-oversize-'.uniqid();
    storeGeminiJobMetadata($jobId, $file, $path);

    $job = new ProcessFileGemini($jobId);

    expect(fn () => $job->handle())->toThrow(GeminiApiException::class);

    $file->refresh();

    expect($file->status)->toBe('failed');
});

function storeGeminiJobMetadata(string $jobId, File $file, string $s3Path): void
{
    JobMetadataPersistence::store($jobId, [
        'fileId' => $file->id,
        'fileGuid' => $file->guid,
        'fileExtension' => $file->fileExtension ?? 'pdf',
        's3OriginalPath' => $s3Path,
        'jobName' => 'Gemini Feature Test',
    ]);
}
