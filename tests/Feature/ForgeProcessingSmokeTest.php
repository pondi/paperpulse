<?php

use App\Models\Document;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Notifications\BulkOperationCompleted;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use App\Services\FileProcessingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('processes an office upload through native database workers and serves its owned assets', function (): void {
    if (! getenv('PAPERPULSE_OFFICE_RUNTIME') || DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires Ubuntu Office runtime and the PostgreSQL test database.');
    }
    $this->withoutVite();
    config(['queue.default' => 'database', 'cache.default' => 'database', 'broadcasting.default' => 'log',
        'ai.file_processing_provider' => 'gemini', 'ai.providers.gemini.api_key' => 'test']);
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['totalTokens' => 100, 'candidates' => [['finishReason' => 'STOP',
        'content' => ['parts' => [['text' => json_encode(['document_title' => 'Forge fixture', 'document_type' => 'report', 'summary' => 'An isolated native worker fixture', 'confidence_score' => 0.98])]]]]]])]);
    $this->mock(GeminiFileManager::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')->once()->andReturn(['fileUri' => 'https://test/file', 'name' => 'files/test', 'mimeType' => 'application/pdf']);
        $mock->shouldReceive('waitUntilActive')->once();
        $mock->shouldReceive('deleteFile')->once()->andReturnTrue();
    });
    $this->mock(GeminiTypeClassifier::class)->shouldReceive('classify')->once()
        ->andReturn(new ClassificationResult('document', 0.99, 'Fixture document'));
    $user = User::factory()->create();
    $content = file_get_contents(base_path('tests/fixtures/office/fixture.docx'));
    $result = app(FileProcessingService::class)->processFile([
        'content' => $content, 'fileName' => 'fixture.docx', 'extension' => 'docx',
        'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => strlen($content), 'source' => 'upload',
    ], 'document', $user->id);
    expect($result['success'])->toBeTrue();
    $workerErrors = [];
    Queue::exceptionOccurred(function ($event) use (&$workerErrors): void {
        $workerErrors[] = $event->exception->getMessage();
    });
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => implode(',', config('queue.worker_queues')),
        '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--memory' => 512, '--no-interaction' => true])->assertSuccessful();
    $file = File::findOrFail($result['fileId']);
    expect($file->status)->toBe('completed', json_encode($workerErrors));
    expect($file->s3_archive_path)->not->toBeNull();
    expect($file->s3_image_path)->not->toBeNull();
    expect(JobHistory::where('uuid', $result['jobId'])->value('status'))->toBe('completed');
    $document = Document::where('file_id', $file->id)->firstOrFail();
    $this->actingAs($user)->get(route('documents.download', $document))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('documents.download', $document))->assertNotFound();
    $user->notify((new BulkOperationCompleted('export', 1))->onConnection('database'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--stop-when-empty' => true, '--sleep' => 0, '--memory' => 512, '--no-interaction' => true])->assertSuccessful();
    expect($user->notifications()->count())->toBe(1);
    $this->artisan('queue:restart', ['--no-interaction' => true])->assertSuccessful();
    expect(Cache::get('illuminate:queue:restart'))->not->toBeNull();
    expect(DB::table('failed_jobs')->count())->toBe(0);
});
