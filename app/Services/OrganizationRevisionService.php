<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\OrganizationInputChange;
use App\Models\OrganizationState;
use App\Models\User;
use App\Models\UserPreference;
use Closure;
use Illuminate\Database\Eloquent\Model;

class OrganizationRevisionService
{
    private int $suppressionDepth = 0;

    public function withoutTracking(Closure $callback): mixed
    {
        $this->suppressionDepth++;
        try {
            return $callback();
        } finally {
            $this->suppressionDepth--;
        }
    }

    public function record(Model $model): void
    {
        if ($this->suppressionDepth > 0) {
            return;
        }
        $userId = (int) $model->getAttribute('user_id');
        if ($userId <= 0) {
            return;
        }
        $model->getConnection()->transaction(function () use ($model, $userId): void {
            User::query()->lockForUpdate()->findOrFail($userId);
            $state = $this->state($userId);
            $state->update(['revision' => $state->revision + 1, 'debounce_until' => now()->addSeconds(30), 'input_fingerprint' => null]);
            $type = strtolower(class_basename($model));
            OrganizationInputChange::query()->updateOrCreate(['user_id' => $userId, 'change_key' => $type.':'.$model->getKey()],
                ['entity_type' => $type, 'entity_id' => $model->getKey(), 'revision' => $state->revision]);
        });
    }

    public function state(int $userId): OrganizationState
    {
        return OrganizationState::query()->firstOrCreate(['user_id' => $userId]);
    }

    public function hasChanges(int $userId): bool
    {
        $state = $this->state($userId);

        return $state->revision > $state->analyzed_revision;
    }

    /** @return array{revision: int, fingerprint: string, changes: list<array<string, mixed>>} */
    public function snapshot(int $userId): array
    {
        return (new OrganizationState)->getConnection()->transaction(function () use ($userId): array {
            User::query()->lockForUpdate()->findOrFail($userId);
            $state = $this->state($userId);
            $fingerprint = $state->input_fingerprint ?? $this->fingerprint($userId);
            $state->update(['input_fingerprint' => $fingerprint]);

            return ['revision' => $state->revision, 'fingerprint' => $fingerprint,
                'changes' => OrganizationInputChange::query()->where('user_id', $userId)->orderBy('revision')->limit(250)
                    ->get(['entity_type', 'entity_id', 'revision'])->toArray()];
        });
    }

    public function markAnalyzed(int $userId, int $revision, string $fingerprint): void
    {
        (new OrganizationState)->getConnection()->transaction(function () use ($userId, $revision, $fingerprint): void {
            User::query()->lockForUpdate()->findOrFail($userId);
            $state = $this->state($userId);
            if ($revision > $state->revision || $revision < $state->analyzed_revision) {
                return;
            }
            $state->update(['analyzed_revision' => $revision, 'analyzed_fingerprint' => $fingerprint]);
            OrganizationInputChange::query()->where('user_id', $userId)->where('revision', '<=', $revision)->delete();
        });
    }

    public function semanticSummary(mixed $summary): array
    {
        $summary = is_array($summary) ? $summary : [];
        unset($summary['provenance']);
        if (is_array($summary['keywords'] ?? null)) {
            sort($summary['keywords']);
        }

        return $this->canonical($summary);
    }

    private function fingerprint(int $userId): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, 'organization-input-v1');
        foreach (File::withoutGlobalScope('user')->where('user_id', $userId)->orderBy('id')->cursor() as $file) {
            hash_update($hash, json_encode(['file', $file->id, $this->semanticSummary($file->organization_summary),
                $file->primary_folder_id, $file->placement_source], JSON_THROW_ON_ERROR));
        }
        foreach (Collection::withoutGlobalScope('user')->where('user_id', $userId)
            ->with(['files' => fn ($query) => $query->withoutGlobalScope('user')->where('files.user_id', $userId)->orderBy('files.id')->select('files.id')])
            ->lazyById(100) as $folder) {
            hash_update($hash, json_encode(['folder', $folder->id, $folder->name, $folder->parent_id,
                $folder->is_archived, $folder->is_pinned, $folder->folder_type, $folder->files->modelKeys()], JSON_THROW_ON_ERROR));
        }
        foreach (OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->orderBy('id')->cursor() as $alias) {
            hash_update($hash, json_encode(['alias', $alias->kind, $alias->alias_key, $alias->canonical_name], JSON_THROW_ON_ERROR));
        }

        $preference = UserPreference::query()->where('user_id', $userId)->first();
        hash_update($hash, json_encode(['automatic', $preference?->auto_organize_documents ?? true,
            $preference?->organization_naming_rules, $preference?->organization_feedback_reset_at?->toIso8601String()], JSON_THROW_ON_ERROR));

        return hash_final($hash);
    }

    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonical($value);
            }
        }

        return $data;
    }
}
