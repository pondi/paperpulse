<?php

namespace App\Services;

use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\User;
use App\Models\UserPreference;

class FolderOrganizationService
{
    public function __construct(private FolderTreeService $tree) {}

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
            $summary = $locked->organization_summary;
            if (! is_array($summary) || ($summary['version'] ?? null) !== OrganizationSummaryNormalizer::VERSION) {
                return $this->initializeInbox($locked);
            }
            $property = is_string($summary['property_address'] ?? null) ? $summary['property_address'] : null;
            $employer = is_array($summary['employer'] ?? null) && is_string($summary['employer']['name'] ?? null) ? $summary['employer'] : null;
            $role = in_array($summary['role'] ?? null, OrganizationSummaryNormalizer::ROLES, true) ? $summary['role'] : 'other';
            $confidence = $summary['confidence'] ?? 0;
            if ((! is_int($confidence) && ! is_float($confidence)) || ! is_finite((float) $confidence) || $confidence < 0.8 || $confidence > 1 || $role === 'other' || (bool) $property === (bool) $employer) {
                return $this->review($locked);
            }
            $rules = app(OrganizationFeedbackService::class)->rules($locked->user_id);
            $targetName = $rules['role_labels'][$role] ?? ucfirst($role);
            $targetType = 'document_role';
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
            $root = $this->tree->ensureFolder($locked->user_id, $rootName, type: 'group_root', source: 'system');
            if ($root->is_archived) {
                return $this->review($locked);
            }
            $identity = $property ?? (empty($employer['registration']) ? $employer['name'] : 'registration:'.$employer['registration']);
            $label = $this->canonicalName($locked->user_id, $property ? 'property' : 'employer', $identity, $property ?? $employer['name']);
            $group = $this->tree->ensureFolder($locked->user_id, $label, $root->id, $property ? 'property' : 'employer', 'system');
            if ($group->is_archived) {
                return $this->review($locked);
            }
            $target = $this->tree->ensureFolder($locked->user_id, $targetName, $group->id, $targetType, 'system');
            if ($target->is_archived || $this->tree->hasSharing($target)) {
                return $this->review($locked);
            }

            return $this->tree->place($locked, $target, 'system');
        });
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
