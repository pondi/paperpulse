<?php

use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Route;
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
