<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationRecommendation;
use App\Models\OrganizationRun;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrganizationDecisionService
{
    public function __construct(private FolderTreeService $tree, private OrganizationPlanner $planner, private OrganizationRevisionService $revisions, private OrganizationRunService $runs) {}

    public function decide(int $userId, array $ids, string $decision, ?string $reason = null, bool $removeEmpty = false): void
    {
        (new OrganizationRecommendation)->getConnection()->transaction(function () use ($userId, $ids, $decision, $reason, $removeEmpty): void {
            $owner = User::query()->lockForUpdate()->findOrFail($userId);
            $recommendations = OrganizationRecommendation::withoutGlobalScope('user')->where('user_id', $userId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($recommendations->count() === count(array_unique($ids)), 404);
            $runIds = [];
            foreach ($recommendations as $recommendation) {
                $run = OrganizationRun::withoutGlobalScope('user')->where('user_id', $userId)->findOrFail($recommendation->organization_run_id);
                if (! in_array($recommendation->status, ['pending', 'conflict'], true)) {
                    continue;
                }
                if ($run->status !== 'awaiting_decisions') {
                    throw ValidationException::withMessages(['organization' => 'Wait until this run finishes generating recommendations.']);
                }
                if ($decision === 'decline') {
                    $recommendation->update(['status' => 'declined', 'decision_reason' => $reason, 'decided_at' => now()]);
                } else {
                    try {
                        $recommendation->getConnection()->transaction(fn () => $this->revisions->withoutTracking(fn () => $this->apply($owner, $recommendation, $removeEmpty)));
                    } catch (ValidationException $exception) {
                        $recommendation->refresh()->update(['status' => 'conflict', 'decision_reason' => 'Documents, folders or sharing changed. Decline this suggestion to keep your current organization.']);
                    }
                }
                $runIds[$run->id] = $run;
            }
            foreach ($runIds as $run) {
                $this->runs->finish($run);
            }
        });
    }

    private function apply(User $owner, OrganizationRecommendation $recommendation, bool $removeEmpty): void
    {
        $before = $recommendation->before_state;
        [$folders, $files] = $this->matchingState($owner, $before);
        $operation = $recommendation->operation;
        $type = $operation['type'];
        if (! in_array($type, ['create', 'rename', 'merge', 'move'], true)
            || array_diff($operation['file_ids'], array_keys($before['files'])) !== []) {
            $this->conflict();
        }
        foreach (array_filter([$operation['folder_id'] ?? null, $operation['target_id'] ?? null, $operation['parent_id'] ?? null]) as $id) {
            if (! isset($folders[$id])) {
                $this->conflict();
            }
        }
        foreach ($folders as $folder) {
            Gate::forUser($owner)->authorize('manageTree', $folder);
            if ($folder->is_pinned || $folder->is_archived || $this->tree->hasSharing($folder)) {
                $this->conflict();
            }
        }
        foreach ($files as $file) {
            if ($file->placement_source === 'manual') {
                $this->conflict();
            }
        }
        if ($type === 'create') {
            $parentId = $operation['parent_id'] ?? null;
            if (Collection::withoutGlobalScope('user')->where('user_id', $owner->id)
                ->where('identity_key', Collection::folderIdentity($operation['name'], $parentId, 'folder'))->exists()) {
                $this->conflict();
            }
            $created = $this->tree->ensureFolder($owner->id, $operation['name'], $parentId, source: 'recommendation');
            $folders[$created->id] = $created;
        } elseif ($type === 'rename') {
            $folder = $folders[$operation['folder_id']];
            $folders[$folder->id] = $this->tree->update($folder, ['name' => $operation['name'], 'parent_id' => $operation['parent_id'] ?? $folder->parent_id]);
        } else {
            $target = $folders[$operation['target_id']];
            foreach ($operation['file_ids'] as $id) {
                $file = $files[$id];
                if ($type === 'move' || $file->primary_folder_id === $operation['folder_id']) {
                    $files[$id] = $this->tree->place($file, $target, 'recommendation');
                } else {
                    $target->files()->syncWithoutDetaching([$id]);
                }
                if ($type === 'merge') {
                    $folders[$operation['folder_id']]->files()->detach($id);
                }
            }
            if ($type === 'merge' && $removeEmpty) {
                $source = $folders[$operation['folder_id']];
                if ($source->files()->withoutGlobalScope('user')->exists() || $source->children()->withoutGlobalScope('user')->exists()) {
                    $this->conflict();
                }
                $this->tree->deleteLeaf($source);
            }
        }
        $after = ['folders' => [], 'files' => []];
        foreach ($folders as $id => $folder) {
            $after['folders'][$id] = $this->planner->folderState(Collection::withoutGlobalScope('user')->withTrashed()->findOrFail($id));
        }
        foreach ($files as $id => $file) {
            $after['files'][$id] = $this->planner->fileState($file->fresh());
        }
        $recommendation->update(['status' => 'applied', 'after_state' => $after, 'decided_at' => now(), 'decision_reason' => null]);
    }

    public function undo(int $userId, int $id): void
    {
        (new OrganizationRecommendation)->getConnection()->transaction(function () use ($userId, $id): void {
            $owner = User::query()->lockForUpdate()->findOrFail($userId);
            $recommendation = OrganizationRecommendation::withoutGlobalScope('user')->where('user_id', $userId)->lockForUpdate()->findOrFail($id);
            if ($recommendation->undone_at) {
                return;
            }
            if ($recommendation->status !== 'applied') {
                $this->conflict();
            }
            [$folders, $files] = $this->matchingState($owner, $recommendation->after_state);
            $before = $recommendation->before_state;
            $this->revisions->withoutTracking(function () use ($folders, $files, $before): void {
                foreach ($folders as $id => $folder) {
                    if ($this->tree->hasSharing($folder)) {
                        $this->conflict();
                    }
                    if (! isset($before['folders'][$id])) {
                        if ($folder->files()->withoutGlobalScope('user')->exists() || $folder->children()->withoutGlobalScope('user')->exists()) {
                            $this->conflict();
                        }
                        $this->tree->deleteLeaf($folder);

                        continue;
                    }
                    if ($folder->trashed()) {
                        $folder->restore();
                    }
                    $this->tree->update($folder, ['name' => $before['folders'][$id]['name'], 'parent_id' => $before['folders'][$id]['parent_id']]);
                }
                foreach ($files as $id => $file) {
                    $previous = $before['files'][$id];
                    if ($previous['primary_folder_id']) {
                        $folder = Collection::withoutGlobalScope('user')->where('user_id', $file->user_id)->findOrFail($previous['primary_folder_id']);
                        if ($this->tree->hasSharing($folder)) {
                            $this->conflict();
                        }
                        $this->tree->place($file, $folder, $previous['placement_source'] ?? 'system');
                    } else {
                        $file->update(['primary_folder_id' => null, 'placement_source' => $previous['placement_source'], 'placement_version' => $file->placement_version + 1]);
                    }
                    $file->collections()->sync($previous['memberships']);
                }
            });
            $recommendation->update(['undone_at' => now()]);
        });
    }

    private function matchingState(User $owner, array $state): array
    {
        $folders = [];
        $files = [];
        foreach ($state['folders'] as $id => $expected) {
            $folder = Collection::withoutGlobalScope('user')->withTrashed()->where('user_id', $owner->id)->lockForUpdate()->find($id);
            if (! $folder || $this->planner->folderState($folder) !== $expected) {
                $this->conflict();
            }
            Gate::forUser($owner)->authorize('manageTree', $folder);
            $folders[$id] = $folder;
        }
        foreach ($state['files'] as $id => $expected) {
            $file = File::withoutGlobalScope('user')->where('user_id', $owner->id)->lockForUpdate()->find($id);
            if (! $file || $this->planner->fileState($file) !== $expected) {
                $this->conflict();
            }
            $files[$id] = $file;
        }

        return [$folders, $files];
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['organization' => 'This recommendation conflicts with the current organization.']);
    }
}
