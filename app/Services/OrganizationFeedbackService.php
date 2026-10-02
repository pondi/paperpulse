<?php

namespace App\Services;

use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\OrganizationRecommendation;
use App\Models\User;
use App\Models\UserPreference;

class OrganizationFeedbackService
{
    public function save(int $userId, array $data): void
    {
        (new UserPreference)->getConnection()->transaction(function () use ($userId, $data): void {
            User::query()->lockForUpdate()->findOrFail($userId);
            $preference = UserPreference::query()->firstOrCreate(['user_id' => $userId]);
            if ($data['reset'] ?? false) {
                OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->delete();
                $preference->update(['organization_naming_rules' => null, 'organization_feedback_reset_at' => now()]);

                return;
            }
            $preference->update(['organization_naming_rules' => $data['naming_rules']]);
            foreach ($data['aliases'] as $input) {
                $alias = empty($input['id'])
                    ? OrganizationAlias::withoutGlobalScope('user')->firstOrNew(['user_id' => $userId, 'kind' => $input['kind'], 'alias_key' => OrganizationAlias::key($input['alias'])])
                    : OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->findOrFail($input['id']);
                $alias->fill(['kind' => $input['kind'], 'canonical_name' => trim($input['canonical_name'])])->save();
            }
            foreach (OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->whereIn('id', $data['removed_alias_ids'] ?? [])->lazyById(100) as $alias) {
                $alias->delete();
            }
        });
    }

    public function rules(int $userId): array
    {
        return UserPreference::query()->where('user_id', $userId)->value('organization_naming_rules') ?? [];
    }

    public function suppressed(int $userId, string $signature): bool
    {
        $reset = UserPreference::query()->where('user_id', $userId)->value('organization_feedback_reset_at');

        return OrganizationRecommendation::withoutGlobalScope('user')->where('user_id', $userId)->where('signature', $signature)
            ->where('status', 'declined')->when($reset, fn ($query) => $query->where('decided_at', '>', $reset->format('Y-m-d H:i:s.u')))->exists();
    }

    public function protectedFile(File $file): bool
    {
        if ($file->placement_source === 'manual') {
            return true;
        }

        $file->loadMissing(['primaryFolder' => fn ($query) => $query->withoutGlobalScope('user')->where('user_id', $file->user_id)]);

        return $file->primaryFolder?->is_pinned || $file->primaryFolder?->organization_source === 'manual';
    }
}
