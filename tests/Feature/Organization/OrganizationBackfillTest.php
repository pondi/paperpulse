<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Exceptions\AIResponseException;
use App\Jobs\Organization\BackfillOrganization;
use App\Models\Collection;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\OrganizationBackfill;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\FolderTreeService;
use App\Services\OrganizationBackfillService;
use App\Services\OrganizationRunService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
    Http::preventStrayRequests();
    $this->analysis = Mockery::mock(TextAnalysisContract::class);
    $this->analysis->shouldReceive('getProviderName')->andReturn('backfill-fake');
    app()->instance(TextAnalysisContract::class, $this->analysis);
});

function backfillOptions(bool $extract = false): array
{
    return ['extract_missing' => $extract, 'max_calls' => 10, 'max_tokens' => 160000];
}

function archiveFile(int $userId, ?array $summary = null): File
{
    return File::factory()->create(['user_id' => $userId, 'status' => 'completed', 's3_original_path' => 'unchanged.pdf',
        'organization_summary' => $summary ?? ['version' => 1, 'property_address' => '42 Birch Road', 'role' => 'contracts', 'confidence' => .95]]);
}

function archiveEntity(File $file, array $data): Document
{
    $entity = Document::factory()->create($data + ['user_id' => $file->user_id, 'file_id' => $file->id]);
    ExtractableEntity::query()->create(['user_id' => $file->user_id, 'file_id' => $file->id, 'entity_type' => 'document',
        'entity_id' => $entity->id, 'is_primary' => true, 'extracted_at' => now()]);

    return $entity;
}

it('previews without writes then organizes bounded owner batches while preserving manual choices', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $files = collect(range(1, 27))->map(fn () => archiveFile($owner->id));
    $foreign = archiveFile($other->id);
    $tree = app(FolderTreeService::class);
    $manualFolder = $tree->ensureFolder($owner->id, 'Manual');
    $manual = $tree->place(archiveFile($owner->id), $manualFolder);
    $pinnedFolder = $tree->ensureFolder($owner->id, 'Pinned', source: 'system');
    $pinnedFolder->update(['is_pinned' => true]);
    $pinned = $tree->place(archiveFile($owner->id), $pinnedFolder, 'system');
    $foldersBefore = Collection::query()->count();
    $this->analysis->shouldNotReceive('analyze');
    $service = app(OrganizationBackfillService::class);
    $preview = $service->preview($owner->id);
    expect($preview['eligible'])->toBe(27)->and($preview['sample'])->toHaveCount(10)
        ->and($preview['paid_calls_without_extraction'])->toBe(0)->and(OrganizationBackfill::query()->count())->toBe(0)
        ->and(Collection::query()->count())->toBe($foldersBefore);
    $backfill = $service->start($owner->id, backfillOptions());
    expect($service->start($owner->id, backfillOptions())->id)->toBe($backfill->id)
        ->and(app(OrganizationRunService::class)->start($owner->id))->toBeNull();
    $later = archiveFile($owner->id);
    $job = new BackfillOrganization($owner->id, $backfill->id);
    expect($job->connection)->toBe('database');
    $job->handle($service);
    expect($backfill->fresh()->processed)->toBe(25)->and($backfill->fresh()->status)->toBe('queued');
    $job->handle($service);
    $job->handle($service);
    expect($backfill->fresh()->processed)->toBe(27)->and($backfill->fresh()->status)->toBe('completed')
        ->and($backfill->fresh()->calls)->toBe(0)->and($manual->fresh()->primary_folder_id)->toBe($manualFolder->id)
        ->and($pinned->fresh()->primary_folder_id)->toBe($pinnedFolder->id)
        ->and($foreign->fresh()->primary_folder_id)->toBeNull()->and($later->fresh()->primary_folder_id)->toBeNull();
    foreach ($files as $file) {
        expect($file->fresh()->s3_original_path)->toBe('unchanged.pdf')->and($file->fresh()->primary_folder_id)->not->toBeNull();
    }
});

it('extracts only absent grouping metadata from bounded stored text', function () {
    $owner = User::factory()->create();
    $ready = archiveFile($owner->id);
    $missing = archiveFile($owner->id);
    $missing->update(['organization_summary' => null]);
    archiveEntity($missing, ['extracted_text' => str_repeat('Lease for 42 Birch Road. ', 500)]);
    $this->analysis->shouldReceive('analyze')->once()->andReturnUsing(function (string $prompt, array $schema): array {
        expect(strlen($prompt))->toBeLessThan(3500)->and($prompt)->toContain('evidence-backed collection subjects', 'never incidental mentions', 'untrusted data');
        expect($schema['required'])->toBe(['organization']);
        ProcessingUsageBudget::reserve(strlen($prompt) + 100);
        ProcessingUsageBudget::record(100, 100);

        return ['organization' => ['group_path' => [], 'property_address' => '42 Birch Road', 'address_kind' => 'property_subject', 'role' => 'contracts', 'confidence' => .95]];
    });
    $service = app(OrganizationBackfillService::class);
    expect($service->preview($owner->id)['maximum_calls_with_extraction'])->toBe(1);
    $backfill = $service->start($owner->id, backfillOptions(true));
    $job = new BackfillOrganization($owner->id, $backfill->id);
    $job->handle($service);
    $job->handle($service);
    expect($backfill->fresh()->processed)->toBe(2)->and($backfill->fresh()->calls)->toBe(1)
        ->and($missing->fresh()->organization_summary['property_address'])->toBe('42 Birch Road')
        ->and($ready->fresh()->primary_folder_id)->toBe($missing->fresh()->primary_folder_id);
});

it('retains paid usage after provider failure and resumes without exceeding the approved budget', function () {
    $owner = User::factory()->create();
    $file = archiveFile($owner->id);
    $file->update(['organization_summary' => null]);
    archiveEntity($file, ['extracted_text' => 'Lease grouping evidence']);
    $paidCalls = 0;
    $this->analysis->shouldReceive('analyze')->twice()->andReturnUsing(function () use (&$paidCalls): array {
        ProcessingUsageBudget::reserve(1000);
        $paidCalls++;
        throw new RuntimeException('Provider interrupted');
    });
    $service = app(OrganizationBackfillService::class);
    $backfill = $service->start($owner->id, array_replace(backfillOptions(true), ['max_calls' => 1]));
    $job = new BackfillOrganization($owner->id, $backfill->id);
    expect(fn () => $job->handle($service))->toThrow(RuntimeException::class);
    expect($backfill->fresh()->calls)->toBe(1)->and($backfill->fresh()->status)->toBe('failed')->and($backfill->fresh()->cursor)->toBe(0);
    expect(fn () => $job->handle($service))->toThrow(AIResponseException::class);
    expect($paidCalls)->toBe(1)->and($backfill->fresh()->tokens)->toBe(1000);
    $service->resume($owner->id, $backfill->id, backfillOptions());
    $job->handle($service);
    expect($backfill->fresh()->processed)->toBe(1)->and($backfill->fresh()->status)->toBe('completed')->and($backfill->fresh()->calls)->toBe(1);
});

it('preserves stored subject evidence when refreshing a summary from its entity', function (): void {
    $owner = User::factory()->create();
    $file = archiveFile($owner->id);
    archiveEntity($file, ['title' => 'Property lease', 'extracted_text' => 'Stored lease']);
    $this->analysis->shouldNotReceive('analyze');
    $service = app(OrganizationBackfillService::class);
    $backfill = $service->start($owner->id, backfillOptions());

    (new BackfillOrganization($owner->id, $backfill->id))->handle($service);

    expect($file->fresh()->organization_summary['property_address'])->toBe('42 Birch Road')
        ->and($file->fresh()->primaryFolder->name)->toBe('Contracts')
        ->and($file->fresh()->primaryFolder->parent->name)->toBe('42 Birch Road')
        ->and($backfill->fresh()->calls)->toBe(0);
});

it('uses stored extraction without paid calls and files missing source metadata in a general role folder', function () {
    $owner = User::factory()->create();
    $file = archiveFile($owner->id);
    $file->update(['organization_summary' => null]);
    archiveEntity($file, ['metadata' => ['organization_evidence' => ['property_address' => '42 Birch Road',
        'address_kind' => 'property_subject', 'role' => 'contracts', 'confidence' => .95]]]);
    $unavailable = archiveFile($owner->id);
    $unavailable->update(['organization_summary' => null]);
    $this->analysis->shouldNotReceive('analyze');
    $service = app(OrganizationBackfillService::class);
    $backfill = $service->start($owner->id, backfillOptions());
    (new BackfillOrganization($owner->id, $backfill->id))->handle($service);
    expect($backfill->fresh()->processed)->toBe(2)->and($backfill->fresh()->skipped)->toBe(0)
        ->and($file->fresh()->organization_summary['property_address'])->toBe('42 Birch Road')->and($backfill->fresh()->calls)->toBe(0);
});

it('enforces pending review opt out and owner constraints across UI jobs and resume', function () {
    $owner = User::factory()->create();
    archiveFile($owner->id);
    $service = app(OrganizationBackfillService::class);
    $run = app(OrganizationRunService::class)->start($owner->id);
    expect(fn () => $service->start($owner->id, backfillOptions()))->toThrow(ValidationException::class);
    $this->actingAs($owner)->getJson(route('preferences.backfill.preview'))->assertOk()->assertJsonPath('can_start', false);
    app(OrganizationRunService::class)->finish($run);
    $this->post(route('preferences.backfill.start'), backfillOptions())->assertRedirect()->assertSessionHasNoErrors();
    $backfill = OrganizationBackfill::query()->first();
    UserPreference::query()->updateOrCreate(['user_id' => $owner->id], ['auto_organize_documents' => false]);
    (new BackfillOrganization($owner->id, $backfill->id))->handle($service);
    expect($backfill->fresh()->status)->toBe('paused');
    expect(fn () => $service->resume($owner->id, $backfill->id, backfillOptions()))->toThrow(ValidationException::class);
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('preferences.backfill.resume', $backfill->id), backfillOptions())->assertNotFound();
    expect(fn () => (new BackfillOrganization($other->id, $backfill->id))->handle($service))->toThrow(ModelNotFoundException::class);
    expect($backfill->fresh()->status)->toBe('paused');
});

it('saves reusable Work employer year templates and creates nodes only for eligible evidence', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->patch(route('preferences.organization'), ['naming_rules' => ['work_structure' => 'year'], 'aliases' => []])
        ->assertRedirect()->assertSessionHasNoErrors();
    $summary = ['version' => 1, 'employer' => ['name' => 'Employer AS'], 'role' => 'contracts',
        'dates' => ['effective_date' => '2025-01-01'], 'confidence' => .95];
    $first = archiveFile($owner->id, $summary);
    $second = archiveFile($owner->id, $summary);
    $this->analysis->shouldNotReceive('analyze');
    $service = app(OrganizationBackfillService::class);
    $backfill = $service->start($owner->id, backfillOptions());
    (new BackfillOrganization($owner->id, $backfill->id))->handle($service);
    $folder = $first->fresh()->primaryFolder;
    expect($folder->name)->toBe('2025')->and($folder->parent->name)->toBe('Employer AS')
        ->and($folder->parent->parent->name)->toBe('Work')->and($second->fresh()->primary_folder_id)->toBe($folder->id);
    expect(Collection::query()->where('user_id', $owner->id)->count())->toBe(3);
    $this->artisan('organization:backfill', ['user' => $owner->id])->assertSuccessful();
    expect(OrganizationBackfill::query()->count())->toBe(1);
});
