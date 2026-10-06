<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\User;
use App\Models\UserPreference;

class FolderOrganizationService
{
    public const GROUPING_VERSION = 4;

    public function __construct(private FolderTreeService $tree, private PropertyGroupingService $properties) {}

    public function initializeInbox(File $file): File
    {
        return $file->getConnection()->transaction(function () use ($file): File {
            User::query()->lockForUpdate()->findOrFail($file->user_id);
            $locked = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
            if (! $this->enabled($locked->user_id) || $locked->primary_folder_id || $locked->placement_source === 'manual') {
                return $locked;
            }

            $inbox = $this->tree->ensureFolder($locked->user_id, 'Inbox', type: 'inbox', source: 'system');

            return $inbox->is_archived ? $locked : $this->tree->place($locked, $inbox, 'system');
        });
    }

    public function placeFromSummary(File $file): File
    {
        return $file->getConnection()->transaction(function () use ($file): File {
            User::query()->lockForUpdate()->findOrFail($file->user_id);
            $locked = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
            if (! $this->enabled($locked->user_id) || app(OrganizationFeedbackService::class)->protectedFile($locked)) {
                return $locked;
            }
            $locked->update(['meta' => array_merge($locked->meta ?? [], ['organization_grouping_version' => self::GROUPING_VERSION])]);
            $summary = $locked->organization_summary;
            if (! is_array($summary) || ($summary['version'] ?? null) !== OrganizationSummaryNormalizer::VERSION) {
                return $this->initializeInbox($locked);
            }
            $path = app(OrganizationSummaryNormalizer::class)->groupPath(array_key_exists('group_path', $summary) ? $summary['group_path'] : []);
            if ($path === null) {
                return $this->review($locked);
            }
            if (! empty($summary['collection_id'])) {
                $target = Collection::withoutGlobalScope('user')->where('user_id', $locked->user_id)->find($summary['collection_id']);
                if (! $target || ($summary['confidence'] ?? 0) < 0.8 || ! $this->usableBranch($target)) {
                    return $this->review($locked);
                }

                return $this->placeContextPath($locked, $summary, $path, $target);
            }
            if ($path !== []) {
                return $this->placeContextPath($locked, $summary, $path);
            }
            $property = is_string($summary['property_address'] ?? null) ? $summary['property_address'] : null;
            $employer = is_array($summary['employer'] ?? null) && is_string($summary['employer']['name'] ?? null) ? $summary['employer'] : null;
            $role = in_array($summary['role'] ?? null, OrganizationSummaryNormalizer::ROLES, true) ? $summary['role'] : 'other';
            $confidence = $summary['confidence'] ?? 0;
            if ((! is_int($confidence) && ! is_float($confidence)) || ! is_finite((float) $confidence) || $confidence < 0 || (($property || $employer) && $confidence < 0.8) || $confidence > 1 || ($property && $employer)) {
                return $this->review($locked);
            }
            $rules = app(OrganizationFeedbackService::class)->rules($locked->user_id);
            $targetName = $rules['role_labels'][$role] ?? ($role === 'other' ? 'Documents' : ucfirst($role));
            $targetType = 'document_role';
            if (! $property && ! $employer) {
                $target = $this->reusableFolder($locked->user_id, $targetName, null, $targetType);

                return $this->protectedFolder($target)
                    ? $this->review($locked) : $this->tree->place($locked, $target, 'system');
            }
            if ($employer && ($rules['work_structure'] ?? 'role') === 'year') {
                $dates = $summary['dates'] ?? [];
                $date = $dates['document_date'] ?? $dates['effective_date'] ?? $dates['invoice_date'] ?? $dates['creation_date'] ?? null;
                if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return $this->review($locked);
                }
                $targetName = substr($date, 0, 4);
                $targetType = 'document_year';
            }
            $rootName = $property ? ($rules['building_root'] ?? 'Building') : ($rules['work_root'] ?? 'Work');
            $root = $this->reusableFolder($locked->user_id, $rootName, null, 'group_root');
            if ($this->protectedFolder($root)) {
                return $this->review($locked);
            }
            $identity = $property ?? (empty($employer['registration']) ? $employer['name'] : 'registration:'.$employer['registration']);
            $label = $property ? $this->properties->canonicalName($locked->user_id, $property) : $this->canonicalName($locked->user_id, 'employer', $identity, $employer['name']);
            $group = $this->reusableFolder($locked->user_id, $label, $root->id, $property ? 'property' : 'employer');
            if ($this->protectedFolder($group)) {
                return $this->review($locked);
            }
            if ($property) {
                $this->properties->consolidate($group);
                $locked->refresh();
            }
            $target = $this->reusableFolder($locked->user_id, $targetName, $group->id, $targetType);
            if ($this->protectedFolder($target)) {
                return $this->review($locked);
            }

            return $this->tree->place($locked, $target, 'system');
        });
    }

    private function placeContextPath(File $file, array $summary, array $path, ?Collection $parent = null): File
    {
        foreach ($path as $node) {
            if ($node['confidence'] < 0.8) {
                return $this->review($file);
            }
        }
        foreach ($path as $node) {
            $kind = $node['kind'];
            $identity = $node['identifier'] ?? $node['name'];
            $name = $kind === 'property' ? $this->properties->canonicalName($file->user_id, $node['name'])
                : $this->contextName($file->user_id, $parent?->id, $node);
            $type = $kind === 'property' && ! $node['identifier'] ? 'property'
                : ($node['identifier'] ? 'context_'.substr(hash('sha256', $kind.':'.$identity), 0, 22) : 'context_'.$kind);
            $existing = Collection::withoutGlobalScope('user')->where('user_id', $file->user_id)
                ->where('parent_id', $parent?->id)->where('normalized_name', Collection::normalizeIdentity($name))
                ->when($node['identifier'], fn ($query) => $query->where('folder_type', $type))
                ->orderBy('id')->first();
            $parent = $existing ?? $this->tree->ensureFolder($file->user_id, $name, $parent?->id, $type, 'system');
            if ($this->protectedFolder($parent)) {
                return $this->review($file);
            }
            if ($type === 'property') {
                $this->properties->consolidate($parent);
                $file->refresh();
            }
        }
        $role = $summary['role'] ?? 'other';
        if (empty($summary['collection_id']) && in_array($role, OrganizationSummaryNormalizer::ROLES, true) && $role !== 'other') {
            $rules = app(OrganizationFeedbackService::class)->rules($file->user_id);
            $roleName = $rules['role_labels'][$role] ?? ucfirst($role);
            if (Collection::normalizeIdentity($parent->name) !== Collection::normalizeIdentity($roleName)) {
                $parent = $this->reusableFolder($file->user_id, $roleName, $parent->id, 'document_role');
            }
            if ($this->protectedFolder($parent)) {
                return $this->review($file);
            }
        }

        return $this->tree->place($file, $parent, 'system');
    }

    private function contextName(int $userId, ?int $parentId, array $node): string
    {
        $alias = OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->where('kind', $node['kind'])
            ->where('alias_key', OrganizationAlias::key($node['name']))->first();

        return $alias?->canonical_name ?? $this->canonicalName($userId, $node['kind'],
            ($parentId ?? 0).':'.hash('sha256', $node['identifier'] ?? Collection::normalizeIdentity($node['name'])), $node['name']);
    }

    private function protectedFolder(Collection $folder): bool
    {
        return $folder->is_archived || $folder->is_pinned || $this->tree->hasSharing($folder);
    }

    private function reusableFolder(int $userId, string $name, ?int $parentId, string $type): Collection
    {
        return Collection::withoutGlobalScope('user')->where('user_id', $userId)->where('parent_id', $parentId)
            ->where('normalized_name', Collection::normalizeIdentity($name))->orderBy('id')->first()
            ?? $this->tree->ensureFolder($userId, $name, $parentId, $type, 'system');
    }

    private function usableBranch(Collection $folder): bool
    {
        $visited = [];
        while (! in_array($folder->id, $visited, true) && count($visited) < 64) {
            if ($this->protectedFolder($folder) || in_array($folder->folder_type, ['inbox', 'needs_review'], true)) {
                return false;
            }
            $visited[] = $folder->id;
            if ($folder->parent_id === null) {
                return true;
            }
            $folder = Collection::withoutGlobalScope('user')->where('user_id', $folder->user_id)->find($folder->parent_id);
            if (! $folder) {
                return false;
            }
        }

        return false;
    }

    private function enabled(int $userId): bool
    {
        $preference = UserPreference::query()->where('user_id', $userId)->first();

        return $preference?->auto_organize_documents ?? true;
    }

    private function review(File $file): File
    {
        $folder = $this->tree->ensureFolder($file->user_id, 'Needs review', type: 'needs_review', source: 'system');
        if ($folder->is_archived) {
            return $this->initializeInbox($file);
        }

        return $this->tree->place($file, $folder, 'system');
    }

    private function canonicalName(int $userId, string $kind, string $identity, string $name): string
    {
        $alias = OrganizationAlias::withoutGlobalScope('user')->firstOrCreate([
            'user_id' => $userId, 'kind' => $kind, 'alias_key' => OrganizationAlias::key($identity),
        ], ['canonical_name' => mb_substr(trim($name), 0, 180)]);

        return $alias->canonical_name;
    }
}
