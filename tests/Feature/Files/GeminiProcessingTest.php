<?php

use App\Exceptions\GeminiApiException;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\StoragePathBuilder;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Storage::fake('local');
    Queue::fake();
    config(['ai.providers.gemini.api_key' => 'isolated-test']);
    $this->classificationType = 'receipt';
    $this->mimeType = 'image/png';
    $this->mock(FilePreviewManager::class)->shouldReceive('generatePreviewForFile')->andReturn(true);
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        if (str_contains($request->url(), ':countTokens')) {
            return Http::response(['totalTokens' => 100]);
        }
        if (str_contains($request->url(), '/upload/')) {
            return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/file']);
        }
        if ($request->url() === 'https://upload.test/file') {
            return Http::response(['file' => [
                'uri' => 'https://gemini.test/file', 'name' => 'files/fixture',
                'mimeType' => $this->mimeType, 'state' => 'ACTIVE',
            ]]);
        }
        if ($request->method() === 'DELETE') {
            return Http::response([], 200);
        }
        if (str_contains($request->url(), ':generateContent')) {
            $properties = $request['generationConfig']['responseJsonSchema']['properties'];
            $data = isset($properties['document_title'])
                ? ['document_title' => 'Notes', 'document_type' => 'text', 'summary' => 'Plain text notes', 'confidence_score' => 0.99]
                : (isset($properties['merchant_name'])
                    ? ['merchant_name' => 'Fixture Store', 'total_amount' => 10, 'receipt_date' => '2026-01-01', 'description' => 'Purchase', 'category' => 'Groceries', 'confidence_score' => 0.99]
                    : ['document_type' => $this->classificationType, 'confidence' => 0.99, 'reasoning' => 'Fixture classification']);
            if (isset($properties['organization'])) {
                $data['organization'] = ['group_path' => [], 'confidence' => 0.99];
            }

            return Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]]);
        }

        throw new RuntimeException('Unexpected Gemini request: '.$request->url());
    });
});

it('processes a receipt image with gemini and stores metadata', function () {
    $user = User::factory()->create();
    $guid = (string) Str::uuid();
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
    try {
        Storage::disk('paperpulse')->put($path, file_get_contents($pngPath));
    } finally {
        unlink($pngPath);
    }
    $file->update(['s3_original_path' => $path]);

    $jobId = (string) Str::uuid();
    storeGeminiJobMetadata($jobId, $file, $path);

    (new ProcessFileGemini($jobId))->handle();

    $file->refresh();

    expect($file->status)->toBe('completed');
    expect($file->processing_type)->toBe('gemini');
    expect($file->meta['gemini']['provider_response']['classification']['document_type'])->toBe('receipt');
    expect(Receipt::where('file_id', $file->id)->sole()->total_amount)->toBe('10.00');
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/files/fixture'));
    expect($file->meta['gemini']['entities'] ?? null)->toBeArray();
});

it('records document subtype for text files', function () {
    $this->classificationType = 'document';
    $this->mimeType = 'text/plain';
    $user = User::factory()->create();
    $guid = (string) Str::uuid();
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

    $jobId = (string) Str::uuid();
    storeGeminiJobMetadata($jobId, $file, $path);

    (new ProcessFileGemini($jobId))->handle();

    $file->refresh();

    expect($file->meta['gemini']['type'] ?? null)->toBe('document');
    expect(Document::where('file_id', $file->id)->sole()->document_type)->toBe('text');
    expect($file->status)->toBe('completed');
});

it('marks the file as failed on gemini validation errors', function () {
    config(['ai.providers.gemini.max_file_size_mb' => 1]);

    $user = User::factory()->create();
    $guid = (string) Str::uuid();
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

    $jobId = (string) Str::uuid();
    storeGeminiJobMetadata($jobId, $file, $path);

    $job = new ProcessFileGemini($jobId);

    $caughtException = null;
    try {
        $job->handle();
    } catch (GeminiApiException $e) {
        $caughtException = $e;
        // Simulate queue worker calling failed() after max retries
        $job->failed($e);
    }

    expect($caughtException)->toBeInstanceOf(GeminiApiException::class);

    $file->refresh();

    expect($file->status)->toBe('failed');
    Http::assertNothingSent();
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
