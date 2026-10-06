<?php

use App\Exceptions\AIResponseException;
use App\Exceptions\GeminiApiException;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\JobHistory;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->withoutVite();
    $this->owner = User::factory()->create();
});

it('serves matching owner-only extraction diagnostics in the API and screen', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id, 'fileName' => 'invoice.pdf', 'status' => 'needs_review', 'meta' => [
        'gemini' => [
            'classification' => ['type' => 'invoice', 'confidence' => 0.8, 'reasoning' => 'Invoice layout', 'private_field' => 'secret'],
            'extraction' => ['confidence_score' => 0.7, 'validation_warnings' => ['Missing due date']],
            'provider_response' => ['raw' => 'private document text'],
        ],
        'processing_coverage' => ['total_pages' => 5, 'processed_pages' => 3, 'complete' => false],
        'review' => ['reason' => 'processing_limit', 'page_limit' => 3, 'private_field' => 'secret'],
        'gemini_error' => ['category' => 'provider_unavailable', 'retryable' => true, 'context' => ['api_key' => 'secret']],
        'unrelated_metadata' => 'secret',
    ]]);
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id]);
    ExtractableEntity::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id,
        'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true,
        'confidence_score' => 0.7, 'extraction_metadata' => ['raw_text' => 'secret']]);
    $this->actingAs($this->owner);
    $api = $this->getJson(route('api.files.extraction-report', $file))->assertOk()
        ->assertJsonPath('data.extraction.validation_warnings.0', 'Missing due date')
        ->assertJsonPath('data.extraction.has_extraction_issues', true)
        ->assertJsonPath('data.coverage.processed_pages', 3)
        ->assertJsonPath('data.entities.0.id', $document->id)
        ->assertDontSee('secret')->assertDontSee('private document text');
    $this->get(route('files.extraction-report', $file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Files/ExtractionReport')->where('report', $api->json('data')));
    $this->get(route('files.show', $file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('file.can_view_extraction_report', true)
            ->where('file.extraction.has_extraction_issues', true));
});

it('does not reveal extraction diagnostics to strangers or shared-file recipients', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id]);
    $recipient = User::factory()->create();
    FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $this->owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now()]);
    foreach ([$recipient, User::factory()->create()] as $user) {
        $this->actingAs($user)->getJson(route('api.files.extraction-report', $file))->assertNotFound();
        $this->get(route('files.extraction-report', $file))->assertNotFound();
    }
    $this->actingAs($recipient)->get(route('files.show', $file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('file.can_view_extraction_report', false)->missing('file.extraction'));
});

it('handles pending legacy and deleted files without exposing unavailable reports', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'pending', 'meta' => null]);
    $this->actingAs($this->owner)->getJson(route('api.files.extraction-report', $file))->assertOk()
        ->assertJsonPath('data.entities', [])->assertJsonPath('data.classification', [])
        ->assertJsonPath('data.extraction.validation_warnings', []);
    $file->delete();
    $this->getJson(route('api.files.extraction-report', $file))->assertNotFound();
    $this->get(route('files.extraction-report', $file))->assertNotFound();
});

it('requires authentication for extraction reports', function (): void {
    $this->get(route('files.extraction-report', 1))->assertRedirect(route('login'));
    $this->getJson(route('api.files.extraction-report', 1))->assertUnauthorized();
});

it('connects voucher details to its actual original file', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id]);
    $voucher = Voucher::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id, 'id' => 99]);
    $this->actingAs($this->owner)->get(route('vouchers.show', $voucher))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('voucher.file.id', $file->id));
    $this->get(route('files.show', $file))->assertOk();
});

it('registers every literal frontend route name', function (): void {
    $missing = [];
    foreach (Filesystem::allFiles(resource_path('js')) as $file) {
        preg_match_all('/\broute\(\s*[\'"]([\w.-]+)[\'"]/', $file->getContents(), $matches);
        foreach (array_unique($matches[1]) as $name) {
            if (! Route::has($name)) {
                $missing[] = $file->getRelativePathname().': '.$name;
            }
        }
    }
    expect($missing)->toBe([]);
});

it('shows durable queued active retry and terminal job timing in workspace activity and reports', function (): void {
    $this->freezeTime();
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'pending']);
    $job = JobHistory::create(['uuid' => 'timing-parent', 'file_id' => $file->id, 'name' => 'Extract document', 'queue' => 'files',
        'status' => 'pending', 'created_at' => now()->subMinutes(3)]);
    $this->actingAs($this->owner)->getJson(route('api.files.extraction-report', $file))->assertOk()
        ->assertJsonPath('data.processing.state', 'pending')->assertJsonPath('data.processing.started_at', null);
    $task = JobHistory::create(['uuid' => 'timing-task', 'parent_uuid' => $job->uuid, 'name' => 'Extract text', 'queue' => 'files',
        'status' => 'processing', 'attempt' => 1, 'progress' => 40, 'started_at' => now()->subMinutes(2),
        'exception' => 'secret provider details']);
    $file->update(['status' => 'processing']);
    $this->get(route('files.show', $file))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('file.processing.stage', 'Extract text')->where('file.processing.elapsed_seconds', 120)->where('file.processing.state', 'processing'));
    $this->get(route('files.index', ['file_id' => $file->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('files.data.0.processing.progress', 40)->where('files.data.0.processing.elapsed_seconds', 120));
    $task->update(['status' => 'retrying', 'attempt' => 2]);
    $this->getJson(route('api.files.extraction-report', $file))->assertJsonPath('data.processing.state', 'retrying')
        ->assertJsonPath('data.processing.attempt', 2)->assertDontSee('secret provider details');
    $task->update(['status' => 'completed', 'progress' => 100, 'finished_at' => now()]);
    $job->update(['status' => 'completed', 'finished_at' => now()]);
    $file->update(['status' => 'completed']);
    $this->travel(10)->minutes();
    $this->getJson(route('api.files.extraction-report', $file))->assertJsonPath('data.processing.state', 'completed')
        ->assertJsonPath('data.processing.elapsed_seconds', 120)->assertJsonPath('data.processing.progress', 100);
});

it('records safe source and service failure categories without exposing internal messages', function (string $code, string $category): void {
    $this->freezeTime();
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing']);
    $job = new class('failure-test') extends ProcessFileGemini
    {
        public function record(File $file, Throwable $exception): void
        {
            $this->recordGeminiFailure($file, $exception);
        }
    };
    $job->record($file, new GeminiApiException('secret key and private source', $code, false, ['key' => 'secret']));
    $this->actingAs($this->owner)->getJson(route('api.files.extraction-report', $file))->assertOk()
        ->assertJsonPath('data.failure.category', $category)->assertJsonPath('data.failure.timestamp', now()->toISOString())
        ->assertDontSee('secret')->assertDontSee('private source');
    $this->get(route('files.show', $file))->assertInertia(fn (AssertableInertia $page) => $page->where('file.failure.category', $category));
    $file->update(['status' => 'pending']);
    $this->getJson(route('api.files.extraction-report', $file))->assertJsonPath('data.failure', []);
})->with([['unsupported_mime', 'unsupported_format'], ['file_too_large', 'file_too_large'], ['timeout', 'api_timeout']]);

it('fails non-retryable AI errors on the first worker attempt while retaining transient retries', function (bool $retryable): void {
    config(['broadcasting.default' => 'log']);
    $file = File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id, 'fileGuid' => $file->guid, 'fileExtension' => 'pdf', 's3OriginalPath' => 'source.pdf']);
    $this->mock(WorkerFileManager::class)->shouldReceive('processWithCleanup')->once()->andThrow(
        new AIResponseException('Processing usage budget exceeded: private details', $retryable, AIResponseException::CODE_USAGE_BUDGET_EXCEEDED, ['private' => 'secret'])
    );
    ProcessFileGemini::dispatch($jobId)->onConnection('database')->onQueue('files');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'files', '--once' => true, '--sleep' => 0, '--no-interaction' => true])->assertSuccessful();

    if ($retryable) {
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 1);
        expect($file->fresh()->status)->toBe('processing');
    } else {
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
        expect($file->fresh()->status)->toBe('failed')
            ->and($file->fresh()->meta['gemini_error']['code'])->toBe(AIResponseException::CODE_USAGE_BUDGET_EXCEEDED);
        $this->actingAs($this->owner)->getJson(route('api.files.extraction-report', $file))->assertOk()
            ->assertJsonPath('data.failure.category', 'usage_budget_exceeded')
            ->assertJsonPath('data.failure.retryable', false)->assertDontSee('secret')->assertDontSee('private details');
    }
})->with([false, true]);
