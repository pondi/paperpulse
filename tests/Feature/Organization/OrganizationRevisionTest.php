<?php

use App\Models\File;
use App\Models\OrganizationInputChange;
use App\Models\User;
use App\Services\FolderTreeService;
use App\Services\OrganizationRevisionService;

function analyzedOrganization(int $userId): array
{
    $tracker = app(OrganizationRevisionService::class);
    $snapshot = $tracker->snapshot($userId);
    $tracker->markAnalyzed($userId, $snapshot['revision'], $snapshot['fingerprint']);

    return $snapshot;
}

test('relevant input changes persist revision fingerprints and debounce without unrelated noise', function () {
    $file = File::factory()->create();
    $tracker = app(OrganizationRevisionService::class);
    $first = analyzedOrganization($file->user_id);
    expect($tracker->hasChanges($file->user_id))->toBeFalse();
    $file->update(['status' => 'processing', 'note' => 'Unrelated field', 's3_image_path' => 'image.png']);
    expect($tracker->state($file->user_id)->revision)->toBe($first['revision']);
    $file->update(['organization_summary' => ['version' => 1, 'title' => 'Changed title', 'keywords' => ['b', 'a'], 'provenance' => ['entity_id' => 1]]]);
    $second = analyzedOrganization($file->user_id);
    expect($second['revision'])->toBe($first['revision'] + 1)->and($second['fingerprint'])->not->toBe($first['fingerprint']);
    $file->update(['organization_summary' => ['keywords' => ['a', 'b'], 'title' => 'Changed title', 'version' => 1, 'provenance' => ['entity_id' => 99, 'provider' => 'new_provider']]]);
    expect($tracker->hasChanges($file->user_id))->toBeFalse()->and($tracker->state($file->user_id)->debounce_until->isFuture())->toBeTrue();
});

test('settling an earlier snapshot preserves late changes even to the same file', function () {
    $file = File::factory()->create();
    $tracker = app(OrganizationRevisionService::class);
    $snapshot = $tracker->snapshot($file->user_id);
    $file->update(['organization_summary' => ['title' => 'Later input']]);
    $tracker->markAnalyzed($file->user_id, $snapshot['revision'], $snapshot['fingerprint']);
    expect($tracker->hasChanges($file->user_id))->toBeTrue();
    expect(OrganizationInputChange::query()->where('user_id', $file->user_id)->where('entity_id', $file->id)->value('revision'))
        ->toBe($snapshot['revision'] + 1);
    $latest = analyzedOrganization($file->user_id);
    expect($tracker->hasChanges($file->user_id))->toBeFalse()->and($latest['revision'])->toBe($snapshot['revision'] + 1);
});

test('tenant revisions and change IDs cannot mix identical evidence across users', function () {
    $first = File::factory()->create(['organization_summary' => ['property_address' => 'Same address']]);
    $second = File::factory()->create(['organization_summary' => ['property_address' => 'Same address']]);
    $tracker = app(OrganizationRevisionService::class);
    analyzedOrganization($first->user_id);
    $snapshot = $tracker->snapshot($second->user_id);
    expect($snapshot['changes'])->toHaveCount(1)->and($snapshot['changes'][0]['entity_id'])->toBe($second->id)
        ->and($tracker->hasChanges($first->user_id))->toBeFalse();
});

test('recommendation-owned writes do not dirty inputs and tracking resumes after failure', function () {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $folder = $tree->ensureFolder($owner->id, 'Before');
    $tracker = app(OrganizationRevisionService::class);
    $snapshot = analyzedOrganization($owner->id);
    $tracker->withoutTracking(fn () => $tree->update($folder, ['name' => 'Approved rename']));
    expect($tracker->hasChanges($owner->id))->toBeFalse();
    expect(fn () => $tracker->withoutTracking(function () {
        throw new RuntimeException('Failure');
    }))->toThrow(RuntimeException::class);
    $tree->update($folder, ['name' => 'Manual rename']);
    expect($tracker->state($owner->id)->revision)->toBe($snapshot['revision'] + 1);
});

test('tree archive and safe deletion record affected files while repeated archive is unchanged', function () {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Root');
    $child = $tree->ensureFolder($owner->id, 'Child', $root->id);
    $file = File::factory()->create(['user_id' => $owner->id]);
    $tree->place($file, $child);
    $tracker = app(OrganizationRevisionService::class);
    $first = analyzedOrganization($owner->id);
    $tree->archiveTree($root);
    expect($tracker->hasChanges($owner->id))->toBeTrue();
    $second = analyzedOrganization($owner->id);
    $tree->archiveTree($root);
    expect($tracker->hasChanges($owner->id))->toBeFalse()->and($second['revision'])->toBe($first['revision'] + 1);
    $tree->deleteLeaf($child);
    expect(OrganizationInputChange::query()->where('user_id', $owner->id)->where('change_key', 'file:'.$file->id)->exists())->toBeTrue();
});

test('durable revision rolls back with a rejected transaction', function () {
    $file = File::factory()->create();
    $tracker = app(OrganizationRevisionService::class);
    $snapshot = analyzedOrganization($file->user_id);
    try {
        $file->getConnection()->transaction(function () use ($file) {
            $file->update(['organization_summary' => ['title' => 'Rolled back']]);
            throw new RuntimeException('Reject');
        });
    } catch (RuntimeException) {
    }
    expect($tracker->state($file->user_id)->revision)->toBe($snapshot['revision'])->and($tracker->hasChanges($file->user_id))->toBeFalse();
});

test('manual membership changes are tracked and recommendation membership writes are suppressed', function () {
    $file = File::factory()->create();
    $folder = app(FolderTreeService::class)->ensureFolder($file->user_id, 'Manual');
    $tracker = app(OrganizationRevisionService::class);
    $before = analyzedOrganization($file->user_id);
    $folder->files()->syncWithoutDetaching([$file->id]);
    $attached = analyzedOrganization($file->user_id);
    expect($attached['fingerprint'])->not->toBe($before['fingerprint']);
    $folder->files()->syncWithoutDetaching([$file->id]);
    expect($tracker->hasChanges($file->user_id))->toBeFalse();
    $tracker->withoutTracking(fn () => $folder->files()->detach($file->id));
    expect($tracker->hasChanges($file->user_id))->toBeFalse();
    $folder->files()->attach($file->id);
    analyzedOrganization($file->user_id);
    $folder->files()->detach($file->id);
    expect($tracker->hasChanges($file->user_id))->toBeTrue();
});
