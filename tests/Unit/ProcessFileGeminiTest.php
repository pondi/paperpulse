<?php

namespace Tests\Unit;

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Models\User;
use App\Services\AI\Extractors\Document\DocumentExtractor;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use App\Services\DuplicateDetectionService;
use App\Services\EntityFactory;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\StoragePathBuilder;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

        // Mock WorkerFileManager to bypass S3 download and execute callback with a temp file
        $workerFileManager = Mockery::mock(WorkerFileManager::class);
        $workerFileManager->shouldReceive('processWithCleanup')
            ->once()
            ->andReturnUsing(function ($s3Path, $fileGuid, $extension, $callback) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
                file_put_contents($tmpFile, 'example text content');

                try {
                    return $callback($tmpFile);
                } finally {
                    @unlink($tmpFile);
                }
            });
        $this->app->instance(WorkerFileManager::class, $workerFileManager);

        // Mock GeminiFileManager to bypass Gemini Files API upload
        $fileManager = Mockery::mock(GeminiFileManager::class);
        $fileManager->shouldReceive('uploadFile')
            ->once()
            ->andReturn([
                'fileUri' => 'https://generativelanguage.googleapis.com/v1beta/files/test-file-123',
                'name' => 'files/test-file-123',
                'mimeType' => 'text/plain',
                'sizeBytes' => 20,
            ]);
        $fileManager->shouldReceive('deleteFile')
            ->once()
            ->with('files/test-file-123')
            ->andReturn(true);
        $this->app->instance(GeminiFileManager::class, $fileManager);

        // Mock GeminiTypeClassifier to return a document classification
        $classification = new ClassificationResult(
            type: 'document',
            confidence: 0.95,
            reasoning: 'Text file content detected',
            rawData: ['document_type' => 'document', 'confidence' => 0.95, 'reasoning' => 'Text file content detected']
        );
        $classifier = Mockery::mock(GeminiTypeClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->andReturn($classification);
        $this->app->instance(GeminiTypeClassifier::class, $classifier);

        // Mock FilePreviewManager to skip preview generation
        $previewManager = Mockery::mock(FilePreviewManager::class);
        $previewManager->shouldReceive('generatePreviewForFile')->once();
        $this->app->instance(FilePreviewManager::class, $previewManager);

        // Mock DocumentExtractor (resolved by EntityExtractorFactory::create('document'))
        $documentExtractor = Mockery::mock(DocumentExtractor::class);
        $documentExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'type' => 'document',
                'confidence_score' => 0.95,
                'data' => [
                    'metadata' => ['title' => 'Test Document', 'type' => 'document'],
                    'content' => 'example text content',
                ],
                'validation_warnings' => [],
            ]);
        $this->app->instance(DocumentExtractor::class, $documentExtractor);

        // Mock EntityFactory to return empty entities list
        $entityFactory = Mockery::mock(EntityFactory::class);
        $entityFactory->shouldReceive('createEntitiesFromParsedData')
            ->once()
            ->andReturn([]);
        $this->app->instance(EntityFactory::class, $entityFactory);

        // Mock DuplicateDetectionService (called via flagDuplicateEntities)
        $duplicateService = Mockery::mock(DuplicateDetectionService::class);
        $this->app->instance(DuplicateDetectionService::class, $duplicateService);

        $job = new ProcessFileGemini($jobId);
        $job->handle();

        $file->refresh();
        $this->assertSame('completed', $file->status);
        $this->assertSame('gemini', $file->processing_type);
        $this->assertArrayHasKey('gemini', $file->meta ?? []);
        $this->assertEquals('document', $file->meta['gemini']['type']);
    }
}
