<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use App\Services\FolderOrganizationService;
use App\Services\FolderTreeService;
use App\Services\PropertyGroupingService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

it('normalizes address ranges and removes floor and registry qualifiers', function (string $input, string $expected): void {
    expect(app(PropertyGroupingService::class)->normalize($input))->toBe($expected);
})->with([
    ['Eksempelveien 8-10, 2. Etasje', 'Eksempelveien 8-10'],
    ['Eksempelveien 8, Gnr. 99 Bnr. 999 Eksempelby', 'Eksempelveien 8'],
    ['Eksempelveien 8 og 10', 'Eksempelveien 8-10'],
    ['Eksempelveien 8 – 10', 'Eksempelveien 8-10'],
    ['Eksempelveien 8A', 'Eksempelveien 8A'],
    ['Eksempelveien 8, Oslo', 'Eksempelveien 8, Oslo'],
]);

it('automatically files the three Eksempelveien variants under one property including generic building documents', function (): void {
    $owner = User::factory()->create();
    $files = collect(['Eksempelveien 8-10, 2. Etasje', 'Eksempelveien 8, Gnr. 99 Bnr. 999 Eksempelby', 'Eksempelveien 8 og 10'])
        ->map(fn (string $address): File => File::factory()->create(['user_id' => $owner->id, 'organization_summary' => [
            'version' => 1, 'property_address' => $address, 'employer' => null, 'role' => 'other', 'confidence' => .95,
        ]]));
    $organizer = app(FolderOrganizationService::class);
    foreach ($files as $file) {
        $organizer->placeFromSummary($file);
    }
    expect($files->map(fn (File $file): int => $file->fresh()->primary_folder_id)->unique())->toHaveCount(1);
    $folder = $files->first()->fresh()->primaryFolder;
    expect($folder->name)->toBe('Documents')->and($folder->parent->name)->toBe('Eksempelveien 8-10')
        ->and($folder->parent->parent->name)->toBe('Building');
    expect(Collection::withoutGlobalScope('user')->where('user_id', $owner->id)->where('folder_type', 'property')->count())->toBe(1);
});

it('consolidates existing nested system folders and their memberships without losing references', function (): void {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Building', type: 'group_root', source: 'system');
    $reference = $tree->ensureFolder($owner->id, 'Reference');
    $files = collect(['Eksempelveien 8-10, 2. Etasje', 'Eksempelveien 8, Gnr. 99 Bnr. 999 Eksempelby', 'Eksempelveien 8 og 10'])->map(function (string $address) use ($owner, $tree, $root, $reference): File {
        $group = $tree->ensureFolder($owner->id, $address, $root->id, 'property', 'system');
        $leaf = $tree->ensureFolder($owner->id, 'Invoices', $group->id, 'document_role', 'system');
        $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => [
            'version' => 1, 'property_address' => $address, 'role' => 'invoices', 'confidence' => .95,
        ]]);
        $file->collections()->attach($reference->id);

        return $tree->place($file, $leaf, 'system');
    });
    app(FolderOrganizationService::class)->placeFromSummary($files->first());
    expect($root->children()->count())->toBe(1)->and($root->children()->sole()->name)->toBe('Eksempelveien 8-10');
    foreach ($files as $file) {
        expect($file->fresh()->primaryFolder->name)->toBe('Invoices')->and($file->collections()->whereKey($reference->id)->exists())->toBeTrue();
        expect($file->collections()->wherePivot('is_primary_placement', true)->count())->toBe(1);
    }
});

it('does not join unrelated house numbers or ambiguous overlapping ranges', function (): void {
    $owner = User::factory()->create();
    foreach (['Eksempelveien 8-10', 'Eksempelveien 8-12'] as $address) {
        File::factory()->create(['user_id' => $owner->id, 'organization_summary' => ['version' => 1, 'property_address' => $address, 'confidence' => .95]]);
    }
    $service = app(PropertyGroupingService::class);
    expect($service->canonicalName($owner->id, 'Eksempelveien 8'))->toBe('Eksempelveien 8')
        ->and($service->canonicalName($owner->id, 'Eksempelveien 14'))->toBe('Eksempelveien 14')
        ->and($service->canonicalName(User::factory()->create()->id, 'Eksempelveien 8'))->toBe('Eksempelveien 8');
});

it('preserves pinned manual and shared property branches during consolidation', function (string $protection): void {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Building', type: 'group_root', source: 'system');
    $old = $tree->ensureFolder($owner->id, 'Eksempelveien 8', $root->id, 'property', 'system');
    if ($protection === 'pinned') {
        $old->update(['is_pinned' => true]);
    } elseif ($protection === 'manual') {
        $old->update(['organization_source' => 'manual']);
    } else {
        $old->shares()->create(['shared_by_user_id' => $owner->id, 'shared_with_user_id' => User::factory()->create()->id, 'permission' => 'view', 'shared_at' => now()]);
    }
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => ['version' => 1, 'property_address' => 'Eksempelveien 8-10', 'role' => 'contracts', 'confidence' => .95]]);
    app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($old->fresh())->not->toBeNull()->and($old->fresh()->name)->toBe('Eksempelveien 8');
})->with(['pinned', 'manual', 'shared']);

it('files documents without contextual identities by role without requiring speculative metadata', function (): void {
    $file = File::factory()->create(['organization_summary' => ['version' => 1, 'role' => 'other', 'confidence' => 0.0]]);
    expect(app(FolderOrganizationService::class)->placeFromSummary($file)->primaryFolder->name)->toBe('Documents');
});
