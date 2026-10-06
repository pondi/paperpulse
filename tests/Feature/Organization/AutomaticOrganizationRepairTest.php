<?php

use App\Models\File;
use App\Models\OrganizationBackfill;
use App\Models\OrganizationRun;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FolderOrganizationService;
use App\Services\OrganizationBackfillService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
});

it('supersedes an exhausted failed review and automatically repairs stored property evidence', function (): void {
    $owner = User::factory()->create();
    $files = collect(['Eksempelveien 8-10, 2. Etasje', 'Eksempelveien 8, Gnr. 99 Bnr. 999 Eksempelby', 'Eksempelveien 8 og 10'])->map(fn (string $address): File => File::factory()->create([
        'user_id' => $owner->id, 'status' => 'completed', 'organization_summary' => ['version' => 1, 'property_address' => $address, 'role' => 'other', 'confidence' => .95],
    ]));
    $run = OrganizationRun::query()->create(['user_id' => $owner->id, 'active_user_id' => $owner->id, 'status' => 'failed', 'attempts' => 3, 'input_revision' => 1, 'input_fingerprint' => str_repeat('a', 64)]);
    $this->artisan('organization:repair', ['--user' => $owner->id])->assertSuccessful();
    expect($run->fresh()->active_user_id)->toBeNull();
    $backfill = OrganizationBackfill::query()->sole();
    app(OrganizationBackfillService::class)->process($owner->id, $backfill->id);
    expect($backfill->fresh()->status)->toBe('completed');
    foreach ($files as $file) {
        expect($file->fresh()->primaryFolder->parent->name)->toBe('Eksempelveien 8-10')
            ->and($file->fresh()->meta['organization_grouping_version'])->toBe(FolderOrganizationService::GROUPING_VERSION);
    }
    $this->artisan('organization:repair', ['--user' => $owner->id])->assertSuccessful();
    expect(OrganizationBackfill::query()->count())->toBe(1);
});

it('does not repair opted out owners or displace an active review', function (bool $optOut): void {
    $owner = User::factory()->create();
    File::factory()->create(['user_id' => $owner->id, 'status' => 'completed', 'organization_summary' => ['version' => 1, 'role' => 'other', 'confidence' => .9]]);
    if ($optOut) {
        UserPreference::query()->create(['user_id' => $owner->id, 'auto_organize_documents' => false]);
    } else {
        OrganizationRun::query()->create(['user_id' => $owner->id, 'active_user_id' => $owner->id, 'status' => 'awaiting_decisions', 'input_revision' => 1, 'input_fingerprint' => str_repeat('a', 64)]);
    }
    $this->artisan('organization:repair')->assertSuccessful();
    expect(OrganizationBackfill::query()->count())->toBe(0);
})->with([true, false]);

it('automatically files older completed uploads that have no stored organization summary without a paid extraction', function (): void {
    $file = File::factory()->create(['status' => 'completed', 'file_type' => 'document', 'organization_summary' => null]);
    $this->artisan('organization:repair', ['--user' => $file->user_id])->assertSuccessful();
    $backfill = OrganizationBackfill::query()->sole();
    app(OrganizationBackfillService::class)->process($file->user_id, $backfill->id);
    expect($file->fresh()->primaryFolder->name)->toBe('Documents')->and($backfill->fresh()->calls)->toBe(0)
        ->and($file->fresh()->meta['organization_grouping_version'])->toBe(FolderOrganizationService::GROUPING_VERSION);
});
