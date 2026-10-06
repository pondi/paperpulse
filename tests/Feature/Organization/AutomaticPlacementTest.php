<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\File\FileMetadataService;
use App\Services\FolderOrganizationService;
use App\Services\FolderTreeService;
use App\Services\OrganizationSummaryNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

function placementSummary(array $organization): array
{
    return app(OrganizationSummaryNormalizer::class)->normalize('document', ['title' => 'Evidence', 'organization' => $organization]);
}

test('accepted file records begin in tenant Inbox through both existing metadata creation paths', function () {
    $owner = User::factory()->create();
    $metadata = app(FileMetadataService::class);
    $data = ['fileName' => 'upload.txt', 'extension' => 'txt', 'size' => 8, 'mimeType' => 'text/plain'];
    $first = $metadata->createFileRecordFromData($data, 'first-guid', 'document', $owner->id);
    $second = $metadata->createFileRecordFromUpload(UploadedFile::fake()->createWithContent('upload.txt', 'Document'), 'second-guid', 'document', $owner->id);
    expect($first->primary_folder_id)->toBe($second->primary_folder_id)->and($first->placement_source)->toBe('system');
    expect($first->primaryFolder->name)->toBe('Inbox')->and($first->primaryFolder->user_id)->toBe($owner->id);
    expect(Collection::withoutGlobalScope('user')->where('user_id', $owner->id)->count())->toBe(1);
});

test('address and employer placement reuse normalized tenant paths without changing original files', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $organizer = app(FolderOrganizationService::class);
    $files = [];
    foreach ([[$owner, '12 Birch Road'], [$owner, '12 BIRCH, ROAD'], [$other, '12 Birch Road']] as [$user, $address]) {
        $file = File::factory()->create(['user_id' => $user->id, 's3_original_path' => 'original.pdf', 'organization_summary' => placementSummary([
            'property_address' => $address, 'address_kind' => 'property_subject', 'role' => 'invoices', 'confidence' => 0.92,
        ])]);
        $organizer->initializeInbox($file);
        $files[] = $organizer->placeFromSummary($file);
    }
    expect($files[0]->primary_folder_id)->toBe($files[1]->primary_folder_id)->not->toBe($files[2]->primary_folder_id);
    expect($files[0]->primaryFolder->name)->toBe('Invoices')
        ->and($files[0]->primaryFolder->parent->name)->toBe('12 Birch Road')
        ->and($files[0]->primaryFolder->parent->parent->name)->toBe('Building')
        ->and($files[0]->s3_original_path)->toBe('original.pdf')->and($files[0]->collections()->count())->toBe(1);
    $version = $files[0]->placement_version;
    expect($organizer->placeFromSummary($files[0])->placement_version)->toBe($version);
    foreach (['Employer AS', 'EMPLOYER LIMITED'] as $name) {
        $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => placementSummary([
            'employer_name' => $name, 'employer_registration' => '123456789', 'role' => 'contracts', 'confidence' => 0.95,
        ])]);
        $work[] = $organizer->placeFromSummary($file);
    }
    expect($work[0]->primary_folder_id)->toBe($work[1]->primary_folder_id)
        ->and($work[0]->primaryFolder->parent->parent->name)->toBe('Work');
});

test('weak ambiguous and incidental evidence needs review without speculative property or employer nodes', function (array $evidence) {
    $file = File::factory()->create(['organization_summary' => placementSummary($evidence)]);
    $placed = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($placed->primaryFolder->name)->toBe('Needs review');
    expect(Collection::withoutGlobalScope('user')->where('user_id', $file->user_id)->count())->toBe(1);
})->with([
    [['property_address' => '12 Birch Road', 'address_kind' => 'property_subject', 'role' => 'invoices', 'confidence' => 0.5]],
    [['property_address' => '12 Birch Road', 'address_kind' => 'property_subject', 'employer_name' => 'Employer AS', 'role' => 'contracts', 'confidence' => 0.95]],
]);

test('manual placement and opt out survive later metadata and repeated automatic placement', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => placementSummary([
        'property_address' => '12 Birch Road', 'address_kind' => 'property_subject', 'role' => 'invoices', 'confidence' => 0.95,
    ])]);
    $tree = app(FolderTreeService::class);
    $manual = $tree->ensureFolder($owner->id, 'My folder');
    $placed = $tree->place($file, $manual, 'manual');
    $organizer = app(FolderOrganizationService::class);
    expect($organizer->initializeInbox($placed)->primary_folder_id)->toBe($manual->id);
    expect($organizer->placeFromSummary($placed)->placement_version)->toBe($placed->placement_version);
    $payload = UserPreference::defaultPreferences();
    $payload['auto_organize_documents'] = false;
    $this->actingAs($owner)->patch(route('preferences.update'), $payload)->assertSessionHasNoErrors();
    $new = File::factory()->create(['user_id' => $owner->id]);
    expect($organizer->initializeInbox($new)->primary_folder_id)->toBeNull();
    expect(Collection::withoutGlobalScope('user')->where('user_id', $owner->id)->count())->toBe(1);
});

test('tenant aliases apply before placement and archived roots create no speculative descendants', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    OrganizationAlias::factory()->create(['user_id' => $other->id, 'kind' => 'property',
        'alias_key' => OrganizationAlias::key('12 Birch Road'), 'canonical_name' => 'Foreign alias']);
    OrganizationAlias::factory()->create(['user_id' => $owner->id, 'kind' => 'property',
        'alias_key' => OrganizationAlias::key('12 Birch Road'), 'canonical_name' => 'Home']);
    $file = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => placementSummary([
        'property_address' => '12 Birch Road', 'address_kind' => 'property_subject', 'role' => 'contracts', 'confidence' => 0.95,
    ])]);
    $organizer = app(FolderOrganizationService::class);
    expect($organizer->placeFromSummary($file)->primaryFolder->parent->name)->toBe('Home');
    $root = app(FolderTreeService::class)->ensureFolder($owner->id, 'Work', type: 'group_root', source: 'system');
    $root->update(['is_archived' => true]);
    $second = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => placementSummary([
        'employer_name' => 'Employer AS', 'role' => 'contracts', 'confidence' => 0.95,
    ])]);
    expect($organizer->placeFromSummary($second)->primaryFolder->name)->toBe('Needs review');
    expect($root->children()->count())->toBe(0);
});

test('automatic organization retains explicitly shared membership and never grants new shared folder access', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $organizer = app(FolderOrganizationService::class);
    $inboxFile = $organizer->initializeInbox($file);
    $inbox = $inboxFile->primaryFolder;
    $inbox->shares()->create(['shared_by_user_id' => $owner->id, 'shared_with_user_id' => $recipient->id,
        'permission' => 'view', 'shared_at' => now()]);
    $file->update(['organization_summary' => placementSummary(['property_address' => '12 Birch Road',
        'address_kind' => 'property_subject', 'role' => 'contracts', 'confidence' => 0.95])]);
    $placed = $organizer->placeFromSummary($file);
    expect($placed->collections()->withoutGlobalScope('user')->whereKey($inbox->id)->exists())->toBeTrue();
    expect($recipient->can('view', $placed))->toBeTrue();
    $placed->primaryFolder->shares()->create(['shared_by_user_id' => $owner->id, 'shared_with_user_id' => $recipient->id,
        'permission' => 'view', 'shared_at' => now()]);
    $next = File::factory()->create(['user_id' => $owner->id, 'organization_summary' => $file->organization_summary]);
    $nextPlaced = $organizer->placeFromSummary($next);
    expect($nextPlaced->primaryFolder->name)->toBe('Needs review');
    expect($recipient->can('view', $nextPlaced))->toBeFalse();
});

test('incidental merchant addresses do not require a grouping review for clearly identified receipts', function (): void {
    $file = File::factory()->create(['organization_summary' => placementSummary([
        'property_address' => 'Merchant address', 'address_kind' => 'merchant', 'role' => 'receipts', 'confidence' => .95,
    ])]);
    $placed = app(FolderOrganizationService::class)->placeFromSummary($file);
    expect($placed->primaryFolder->name)->toBe('Receipts')->and($placed->primaryFolder->parent_id)->toBeNull();
});
