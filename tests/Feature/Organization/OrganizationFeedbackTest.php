<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FolderOrganizationService;
use App\Services\FolderTreeService;
use App\Services\OrganizationFeedbackService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRevisionService;
use App\Services\OrganizationRunService;
use App\Services\OrganizationSummaryNormalizer;

beforeEach(fn () => $this->withoutVite());

it('saves owner naming rules and aliases and uses them before provider calls', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('preferences.organization'), [
        'naming_rules' => ['building_root' => 'Homes', 'role_labels' => ['contracts' => 'Agreements']],
        'aliases' => [['kind' => 'property', 'alias' => '12 Birch Road', 'canonical_name' => 'Birch house']],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $file = File::factory()->create(['user_id' => $user->id, 'organization_summary' => [
        'version' => 1, 'property_address' => '12 Birch Road', 'role' => 'contracts', 'confidence' => .95,
    ]]);
    $file = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($file->primaryFolder->name)->toBe('Agreements')->and($file->primaryFolder->parent->name)->toBe('Birch house')
        ->and($file->primaryFolder->parent->parent->name)->toBe('Homes');
    $this->get(route('preferences.index'))->assertInertia(fn ($page) => $page->where('organizationAliases.0.canonical_name', 'Birch house'));
});

it('isolates aliases and validates owned IDs and folder labels', function () {
    $owner = User::factory()->create();
    $foreign = OrganizationAlias::factory()->create();
    $this->actingAs($owner)->patch(route('preferences.organization'), ['naming_rules' => [],
        'aliases' => [['id' => $foreign->id, 'kind' => 'employer', 'canonical_name' => 'Wrong']]])
        ->assertSessionHasErrors('aliases.0.id');
    $this->patch(route('preferences.organization'), ['naming_rules' => ['building_root' => '../../escape'], 'aliases' => []])
        ->assertSessionHasErrors('naming_rules.building_root');
    expect($foreign->fresh()->canonical_name)->not->toBe('Wrong');
});

it('suppresses equivalent unchanged declined operations and resets only the owners feedback', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    $operation = ['type' => 'create', 'parent_id' => null, 'folder_id' => null, 'file_ids' => [], 'name' => 'Homes', 'reason' => 'Reason', 'confidence' => .9];
    $before = ['folders' => [], 'files' => [$file->id => app(OrganizationPlanner::class)->fileState($file)]];
    $signature = app(OrganizationPlanner::class)->signature($operation, $before);
    $recommendation = $run->recommendations()->create(['user_id' => $owner->id, 'operation' => $operation, 'before_state' => $before,
        'signature' => $signature, 'confidence' => .9, 'reason' => 'Reason', 'status' => 'declined', 'decided_at' => now()]);
    $feedback = app(OrganizationFeedbackService::class);
    expect($feedback->suppressed($owner->id, $signature))->toBeTrue()->and($feedback->suppressed($other->id, $signature))->toBeFalse();
    $equivalent = array_reverse(array_replace($operation, ['reason' => 'Different words', 'confidence' => .8]), true);
    expect(app(OrganizationPlanner::class)->signature($equivalent, $before))->toBe($signature);
    $file->update(['organization_summary' => ['title' => 'Material change']]);
    $changed = ['folders' => [], 'files' => [$file->id => app(OrganizationPlanner::class)->fileState($file)]];
    expect($feedback->suppressed($owner->id, app(OrganizationPlanner::class)->signature($operation, $changed)))->toBeFalse();
    $feedback->save($owner->id, ['reset' => true]);
    expect($feedback->suppressed($owner->id, $signature))->toBeFalse()->and($recommendation->fresh()->status)->toBe('declined')
        ->and(app(OrganizationRevisionService::class)->hasChanges($owner->id))->toBeTrue();
});

it('preserves manual placements and pinned primary folders without LLM calls', function () {
    $owner = User::factory()->create();
    $folder = app(FolderTreeService::class)->ensureFolder($owner->id, 'Keep', source: 'system');
    $folder->update(['is_pinned' => true]);
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => ['version' => 1, 'property_address' => 'House', 'role' => 'contracts', 'confidence' => .95]]);
    app(FolderTreeService::class)->place($file, $folder, 'system');
    expect(app(FolderOrganizationService::class)->placeFromSummary($file)->primary_folder_id)->toBe($folder->id);
    $analysis = Mockery::mock(TextAnalysisContract::class);
    $analysis->shouldNotReceive('analyze');
    app()->instance(TextAnalysisContract::class, $analysis);
    $run = app(OrganizationRunService::class)->start($owner->id);
    app(OrganizationPlanner::class)->generate(app(OrganizationRunService::class)->begin($owner->id, $run->id));
    expect($run->fresh()->calls)->toBe(0)->and($run->fresh()->status)->toBe('completed');
    UserPreference::query()->updateOrCreate(['user_id' => $owner->id], ['auto_organize_documents' => false]);
    File::factory()->create(['user_id' => $owner->id]);
    expect(app(OrganizationRunService::class)->start($owner->id))->toBeNull();
});

it('accepts aliases for generic context kinds and applies them during automatic placement', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner)->patch(route('preferences.organization'), ['naming_rules' => [],
        'aliases' => [['kind' => 'person', 'alias' => 'Alex Example', 'canonical_name' => 'Alex documents']],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $summary = app(OrganizationSummaryNormalizer::class)->normalize('document', ['organization' => ['group_path' => [
        ['kind' => 'person', 'name' => 'Alex Example', 'relationship' => 'subject', 'confidence' => .95],
    ]]]);
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => $summary]);
    expect(app(FolderOrganizationService::class)->placeFromSummary($file)->primaryFolder->name)->toBe('Alex documents');
});
