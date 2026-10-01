<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FolderTreeService
{
    public function ensureFolder(int $userId, string $name, ?int $parentId = null, string $type = 'folder', string $source = 'manual'): Collection
    {
        return (new Collection)->getConnection()->transaction(function () use ($userId, $name, $parentId, $type, $source): Collection {
            User::query()->lockForUpdate()->findOrFail($userId);
            $prototype = new Collection(['user_id' => $userId, 'name' => $name, 'parent_id' => $parentId]);
            $prototype->validateTreePosition();

            return Collection::withoutGlobalScope('user')->firstOrCreate([
                'user_id' => $userId, 'identity_key' => Collection::folderIdentity($name, $parentId, $type),
            ], ['name' => trim($name), 'parent_id' => $parentId, 'folder_type' => $type, 'organization_source' => $source]);
        });
    }

    public function place(File $file, Collection $folder, string $source = 'manual'): File
    {
        return $file->getConnection()->transaction(function () use ($file, $folder, $source): File {
            $locked = File::withoutGlobalScope('user')->lockForUpdate()->findOrFail($file->id);
            $target = Collection::withoutGlobalScope('user')->where('user_id', $locked->user_id)->find($folder->id);
            if (! $target || $target->is_archived) {
                throw ValidationException::withMessages(['primary_folder_id' => 'Select an active folder owned by the file owner.']);
            }
            if ($source === 'system' && $locked->placement_source === 'manual') {
                return $locked;
            }
            if ($locked->primary_folder_id === $target->id && $locked->placement_source === $source) {
                return $locked;
            }
            if ($locked->primary_folder_id) {
                $locked->collections()->wherePivot('is_primary_placement', true)->detach($locked->primary_folder_id);
            }
            if (! $locked->collections()->withoutGlobalScope('user')->whereKey($target->id)->exists()) {
                $locked->collections()->attach($target->id, ['is_primary_placement' => true]);
            }
            $locked->update(['primary_folder_id' => $target->id, 'placement_source' => $source,
                'placement_version' => $locked->placement_version + 1]);

            return $locked->fresh();
        });
    }

    /** @return list<int> */
    public function subtreeIds(Collection $root): array
    {
        $ids = [$root->id];
        $frontier = [$root->id];
        while ($frontier !== []) {
            $frontier = Collection::withoutGlobalScope('user')->where('user_id', $root->user_id)
                ->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
            if (count($ids) > 1000) {
                throw ValidationException::withMessages(['collection' => 'This tree is too large for one operation. Work on a smaller branch.']);
            }
        }

        return $ids;
    }

    /** @return array{folders: int, files: int, can_delete: bool, sharing_scope: string} */
    public function preview(Collection $root): array
    {
        $ids = $this->subtreeIds($root);
        $files = File::withoutGlobalScope('user')->where('user_id', $root->user_id)
            ->whereHas('collections', fn ($query) => $query->withoutGlobalScope('user')->whereIn('collections.id', $ids))->count();

        return ['folders' => count($ids), 'files' => $files, 'can_delete' => count($ids) === 1,
            'sharing_scope' => 'Only files directly in each explicitly shared folder are shared.'];
    }

    /** @return list<array{id: int, label: string, href: string}> */
    public function breadcrumbs(Collection $folder): array
    {
        $path = [];
        $current = $folder;
        for ($depth = 0; $current && $depth < 64; $depth++) {
            array_unshift($path, ['id' => $current->id, 'label' => $current->name,
                'href' => route('collections.show', $current->id)]);
            $current = $current->parent_id ? Collection::withoutGlobalScope('user')
                ->where('user_id', $folder->user_id)->find($current->parent_id) : null;
        }

        return $path;
    }

    public function update(Collection $folder, array $data): Collection
    {
        return $folder->getConnection()->transaction(function () use ($folder, $data): Collection {
            User::query()->lockForUpdate()->findOrFail($folder->user_id);
            $locked = Collection::withoutGlobalScope('user')->where('user_id', $folder->user_id)->lockForUpdate()->findOrFail($folder->id);
            $attributes = array_intersect_key($data, array_flip(['name', 'description', 'icon', 'color', 'parent_id', 'is_pinned']));
            if (array_key_exists('icon', $attributes) && $attributes['icon'] === null) {
                $attributes['icon'] = 'folder';
            }
            if (array_key_exists('color', $attributes) && $attributes['color'] === null) {
                $attributes['color'] = '#3B82F6';
            }
            $candidateName = $attributes['name'] ?? $locked->name;
            $candidateParent = array_key_exists('parent_id', $attributes) ? $attributes['parent_id'] : $locked->parent_id;
            if (Collection::withoutGlobalScope('user')->where('user_id', $locked->user_id)
                ->where('identity_key', Collection::folderIdentity($candidateName, $candidateParent, $locked->folder_type))
                ->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['name' => 'A folder with this name already exists in this location.']);
            }
            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    public function archiveTree(Collection $folder, bool $archived = true): Collection
    {
        return $folder->getConnection()->transaction(function () use ($folder, $archived): Collection {
            User::query()->lockForUpdate()->findOrFail($folder->user_id);
            Collection::withoutGlobalScope('user')->where('user_id', $folder->user_id)
                ->whereIn('id', $this->subtreeIds($folder))->update(['is_archived' => $archived]);

            return $folder->fresh();
        });
    }

    public function deleteLeaf(Collection $folder): bool
    {
        return $folder->getConnection()->transaction(function () use ($folder): bool {
            User::query()->lockForUpdate()->findOrFail($folder->user_id);
            $locked = Collection::withoutGlobalScope('user')->where('user_id', $folder->user_id)->lockForUpdate()->findOrFail($folder->id);
            if ($locked->children()->withoutGlobalScope('user')->exists()) {
                throw ValidationException::withMessages(['collection' => 'Move or delete child folders before deleting this folder.']);
            }
            $primaryFiles = File::withoutGlobalScope('user')->where('user_id', $folder->user_id)->where('primary_folder_id', $folder->id);
            $primaryFiles->increment('placement_version');
            $primaryFiles->update(['primary_folder_id' => null, 'placement_source' => null]);
            $locked->files()->detach();

            return $locked->delete();
        });
    }
}
