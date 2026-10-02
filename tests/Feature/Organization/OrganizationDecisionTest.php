<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use App\Services\FolderTreeService;
use App\Services\OrganizationDecisionService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function decisionFixture(string $type = 'move'): array
{
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $source = $tree->ensureFolder($owner->id, 'Before', source: 'system')->refresh();
    $target = $tree->ensureFolder($owner->id, 'After', source: 'system')->refresh();
    $file = File::factory()->create(['user_id' => $owner->id, 's3_original_path' => 'untouched.pdf']);
    $file = $tree->place($file, $source, 'system');
    $planner = app(OrganizationPlanner::class);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    $operation = ['type' => $type, 'folder_id' => $source->id, 'target_id' => $target->id, 'parent_id' => null,
        'name' => 'Renamed', 'file_ids' => [$file->id]];
    $recommendation = $run->recommendations()->create(['user_id' => $owner->id, 'operation' => $operation,
        'before_state' => ['folders' => [$source->id => $planner->folderState($source), $target->id => $planner->folderState($target)],
            'files' => [$file->id => $planner->fileState($file)]], 'signature' => str_repeat('a', 64), 'confidence' => .95, 'reason' => 'Group documents']);
    $runs->finish($run);

    return [$owner, $source, $target, $file, $recommendation, $run];
}

it('applies once audits placement and conditionally undoes without modifying bytes', function () {
    [$owner, $source, $target, $file, $recommendation, $run] = decisionFixture();
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    $version = $file->fresh()->placement_version;
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    expect($file->fresh()->primary_folder_id)->toBe($target->id)->and($file->fresh()->placement_version)->toBe($version);
    expect($recommendation->fresh()->status)->toBe('applied')->and($run->fresh()->status)->toBe('completed');
    $decisions->undo($owner->id, $recommendation->id);
    $decisions->undo($owner->id, $recommendation->id);
    expect($file->fresh()->primary_folder_id)->toBe($source->id)->and($file->fresh()->s3_original_path)->toBe('untouched.pdf')
        ->and($file->collections()->pluck('collections.id')->all())->toBe([$source->id]);
});

it('preserves late manual changes keeps conflicts pending and allows decline', function () {
    [$owner, $source, $target, $file, $recommendation, $run] = decisionFixture();
    app(FolderTreeService::class)->place($file, $target);
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    expect($recommendation->fresh()->status)->toBe('conflict')->and($run->fresh()->status)->toBe('awaiting_decisions')
        ->and($file->fresh()->placement_source)->toBe('manual');
    $decisions->decide($owner->id, [$recommendation->id], 'decline', 'Keep my placement');
    expect($recommendation->fresh()->decision_reason)->toBe('Keep my placement')->and($run->fresh()->status)->toBe('completed');
});

it('refuses undo after newer file or folder changes', function () {
    [$owner, $source, $target, $file, $recommendation] = decisionFixture();
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    app(FolderTreeService::class)->update($target, ['name' => 'New manual name']);
    expect(fn () => $decisions->undo($owner->id, $recommendation->id))->toThrow(ValidationException::class);
    expect($target->fresh()->name)->toBe('New manual name')->and($recommendation->fresh()->undone_at)->toBeNull();
});

it('merges memberships and deletes only explicitly approved empty logical folders', function (bool $removeEmpty) {
    [$owner, $source, $target, $file, $recommendation] = decisionFixture('merge');
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply', removeEmpty: $removeEmpty);
    expect(Collection::withTrashed()->find($source->id)->trashed())->toBe($removeEmpty)
        ->and($file->fresh()->primary_folder_id)->toBe($target->id)->and($file->fresh()->s3_original_path)->toBe('untouched.pdf');
    $decisions->undo($owner->id, $recommendation->id);
    expect($source->fresh()->trashed())->toBeFalse()->and($file->fresh()->primary_folder_id)->toBe($source->id);
})->with([false, true]);

it('rejects foreign decisions and revalidates sharing pinned folders and tree cycles', function (string $change) {
    [$owner, $source, $target, $file, $recommendation] = decisionFixture('rename');
    if ($change === 'pinned') {
        $source->update(['is_pinned' => true]);
    } elseif ($change === 'sharing') {
        $source->shares()->create(['shared_by_user_id' => $owner->id, 'shared_with_user_id' => User::factory()->create()->id, 'permission' => 'view', 'shared_at' => now()]);
    } else {
        $recommendation->update(['operation' => array_replace($recommendation->operation, ['parent_id' => $source->id])]);
    }
    $decisions = app(OrganizationDecisionService::class);
    expect(fn () => $decisions->decide(User::factory()->create()->id, [$recommendation->id], 'apply'))->toThrow(HttpException::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    expect($recommendation->fresh()->status)->toBe('conflict')->and($source->fresh()->name)->toBe('Before');
})->with(['pinned', 'sharing', 'cycle']);

it('creates and renames folders with audited reversible state', function (string $type) {
    [$owner, $source, $target, $file, $recommendation] = decisionFixture($type);
    if ($type === 'create') {
        $recommendation->update(['operation' => ['type' => 'create', 'folder_id' => null, 'target_id' => null, 'parent_id' => null, 'name' => 'Created', 'file_ids' => []],
            'before_state' => ['folders' => [], 'files' => []]]);
    }
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    expect($recommendation->fresh()->status)->toBe('applied');
    if ($type === 'rename') {
        expect($source->fresh()->name)->toBe('Renamed');
    } else {
        expect(Collection::query()->where('user_id', $owner->id)->where('name', 'Created')->exists())->toBeTrue();
    }
    $decisions->undo($owner->id, $recommendation->id);
    expect($source->fresh()->name)->toBe('Before')->and(Collection::query()->where('user_id', $owner->id)->where('name', 'Created')->exists())->toBeFalse();
})->with(['create', 'rename']);
