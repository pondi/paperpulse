<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use App\Services\FolderOrganizationService;
use App\Services\FolderTreeService;
use App\Services\OrganizationSummaryNormalizer;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

function contextSummary(array $path, string $role = 'other'): array
{
    return app(OrganizationSummaryNormalizer::class)->normalize('document', ['title' => 'Synthetic fixture',
        'organization' => ['group_path' => $path, 'role' => $role, 'confidence' => .95]]);
}

function contextNode(string $kind, string $name, string $relationship = 'subject', ?string $identifier = null): array
{
    return ['kind' => $kind, 'name' => $name, 'relationship' => $relationship, 'identifier' => $identifier, 'confidence' => .95];
}

it('reuses evidence-backed groups for arbitrary kinds without an additional provider request', function (string $kind, string $name): void {
    $owner = User::factory()->create();
    $first = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([contextNode($kind, $name)])]);
    $second = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([contextNode($kind, mb_strtoupper($name))])]);
    $organizer = app(FolderOrganizationService::class);
    $placed = $organizer->placeFromSummary($first);
    expect($organizer->placeFromSummary($second)->primary_folder_id)->toBe($placed->primary_folder_id)
        ->and($placed->primaryFolder->name)->toBe($name)
        ->and(Collection::withoutGlobalScope('user')->where('user_id', $owner->id)->count())->toBe(1);
    $version = $placed->placement_version;
    expect($organizer->placeFromSummary($placed)->placement_version)->toBe($version);
})->with([
    ['person', 'Alex Example'], ['project', 'Example restoration'], ['category', 'Education'],
    ['topic', 'Garden planning'], ['vehicle', 'Example vehicle'],
]);

it('preserves person and property subgroups and keeps role folders under the actual context', function (): void {
    $owner = User::factory()->create();
    $path = [contextNode('person', 'Alex Example'), contextNode('category', 'Education', 'subgroup')];
    $first = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary($path, 'letters')]);
    $second = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([
        contextNode('person', 'Blair Example'), contextNode('category', 'Education', 'subgroup'),
    ], 'letters')]);
    $organizer = app(FolderOrganizationService::class);
    $placed = $organizer->placeFromSummary($first);
    expect($placed->primaryFolder->name)->toBe('Letters')->and($placed->primaryFolder->parent->name)->toBe('Education')
        ->and($placed->primaryFolder->parent->parent->name)->toBe('Alex Example')
        ->and($organizer->placeFromSummary($second)->primary_folder_id)->not->toBe($placed->primary_folder_id);
    $building = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([
        contextNode('property', 'Eksempelveien 14-16, 2. Etasje'), contextNode('unit', 'Second floor', 'subgroup'),
    ])]);
    $floor = $organizer->placeFromSummary($building)->primaryFolder;
    expect($floor->name)->toBe('Second floor')->and($floor->parent->name)->toBe('Eksempelveien 14-16');
});

it('uses explicit identifiers to reuse variants while separating equally named entities and owners', function (): void {
    $owner = User::factory()->create();
    $organizer = app(FolderOrganizationService::class);
    $folders = [];
    foreach ([[$owner->id, 'Alex Example', 'example-person-one'], [$owner->id, 'A. Example', 'example-person-one'],
        [$owner->id, 'Alex Example', 'example-person-two'], [User::factory()->create()->id, 'Alex Example', 'example-person-one']] as [$userId, $name, $identifier]) {
        $file = File::factory()->create(['user_id' => $userId, 'organization_summary' => contextSummary([contextNode('person', $name, identifier: $identifier)])]);
        $folders[] = $organizer->placeFromSummary($file)->primary_folder_id;
    }
    expect($folders[0])->toBe($folders[1])->not->toBe($folders[2])->not->toBe($folders[3]);
});

it('rejects weak or unsupported relationships before creating speculative context folders', function (array $path): void {
    $file = File::factory()->create(['organization_summary' => contextSummary($path)]);
    $placed = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($placed->primaryFolder->name)->toBe('Needs review')
        ->and(Collection::withoutGlobalScope('user')->where('user_id', $file->user_id)->count())->toBe(1);
})->with([
    [[array_replace(contextNode('person', 'Alex Example'), ['confidence' => .4])]],
    [[array_replace(contextNode('person', 'Alex Example'), ['relationship' => 'incidental'])]],
    [[contextNode('person', 'Alex Example'), contextNode('project', 'Unrelated project')]],
    [[contextNode('person', '../escape')]],
    [[contextNode('person', 'Alex Example'), contextNode('person', 'Alex Example', 'subgroup')]],
]);

it('does not infer a person or concept group from incidental mentions or keywords', function (): void {
    $summary = app(OrganizationSummaryNormalizer::class)->normalize('document', ['title' => 'Correspondence',
        'entities_mentioned' => [['entity_name' => 'Alex Example', 'entity_type' => 'person']],
        'organization' => ['keywords' => ['Alex Example', 'Education'], 'confidence' => .95]]);
    $file = File::factory()->create(['organization_summary' => $summary]);
    expect(app(FolderOrganizationService::class)->placeFromSummary($file)->primaryFolder->name)->toBe('Documents');
});

it('preserves protected existing context folders and never adds files to shared branches', function (string $protection): void {
    $owner = User::factory()->create();
    $folder = app(FolderTreeService::class)->ensureFolder($owner->id, 'Alex Example', type: 'context_person', source: 'system');
    if ($protection === 'shared') {
        $folder->shares()->create(['shared_by_user_id' => $owner->id, 'shared_with_user_id' => User::factory()->create()->id,
            'permission' => 'view', 'shared_at' => now()]);
    } else {
        $folder->update($protection === 'manual' ? ['organization_source' => 'manual'] : ['is_pinned' => true]);
    }
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([
        contextNode('person', 'Alex Example'), contextNode('category', 'Education', 'subgroup'),
    ])]);
    expect(app(FolderOrganizationService::class)->placeFromSummary($file)->primaryFolder->name)->toBe('Needs review')
        ->and($folder->children()->count())->toBe(0)->and($folder->files()->count())->toBe(0);
})->with(['pinned', 'manual', 'shared']);

it('reuses existing system collection roots rather than duplicating them with a different context type', function (): void {
    $owner = User::factory()->create();
    $root = app(FolderTreeService::class)->ensureFolder($owner->id, 'Homes', type: 'group_root', source: 'system');
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => contextSummary([
        contextNode('category', 'Homes'), contextNode('property', 'Eksempelveien 14', 'subgroup'),
    ])]);
    $placed = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($placed->primaryFolder->parent_id)->toBe($root->id)
        ->and(Collection::withoutGlobalScope('user')->where('user_id', $owner->id)->whereNull('parent_id')->count())->toBe(1);
});

it('does not duplicate a document role already represented by the context leaf', function (): void {
    $file = File::factory()->create(['organization_summary' => contextSummary([
        contextNode('project', 'Example project'), contextNode('category', 'Invoices', 'subgroup'),
    ], 'invoices')]);
    $placed = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($placed->primaryFolder->name)->toBe('Invoices')->and($placed->primaryFolder->parent->name)->toBe('Example project');
});
