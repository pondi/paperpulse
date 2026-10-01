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
}
