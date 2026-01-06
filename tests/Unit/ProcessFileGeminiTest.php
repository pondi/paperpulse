<?php

namespace Tests\Unit;

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\User;
use App\Services\Files\StoragePathBuilder;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessFileGeminiTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_file_gemini_sets_file_completed_and_stores_metadata(): void
    {
        Storage::fake('paperpulse');

        config([
            'ai.providers.gemini.api_key' => 'test-key',
            'ai.providers.gemini.model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"entities": [{"type": "document", "data": {"title": "Test Document", "content": "example text content"}}]}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $guid = 'unit-text-'.uniqid();
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
        Storage::disk('paperpulse')->put($path, 'example text content');
        $file->update(['s3_original_path' => $path]);

        $jobId = 'job-unit-'.uniqid();
        JobMetadataPersistence::store($jobId, [
            'fileId' => $file->id,
            'fileGuid' => $file->guid,
            'fileExtension' => 'txt',
            's3OriginalPath' => $path,
            'jobName' => 'Gemini Test',
        ]);

        $job = new ProcessFileGemini($jobId);
        $job->handle();

        $file->refresh();
        $this->assertSame('completed', $file->status);
        $this->assertSame('gemini', $file->processing_type);
        $this->assertArrayHasKey('gemini', $file->meta ?? []);
        $this->assertEquals('document', $file->meta['gemini']['type']);
    }
}
