<?php

namespace App\Observers;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\UserPreference;
use App\Services\OrganizationRevisionService;
use Illuminate\Database\Eloquent\Model;

class OrganizationInputObserver
{
    public function created(Model $model): void
    {
        if (! $model instanceof UserPreference || $model->getAttribute('auto_organize_documents') === false) {
            app(OrganizationRevisionService::class)->record($model);
        }
    }

    public function saved(Model $model): void
    {
        $tracker = app(OrganizationRevisionService::class);
        if ($model instanceof File) {
            $summaryChanged = $model->wasChanged('organization_summary')
                && $tracker->semanticSummary($model->getOriginal('organization_summary')) !== $tracker->semanticSummary($model->organization_summary);
            if ($summaryChanged || $model->wasChanged(['primary_folder_id', 'placement_source'])) {
                $tracker->record($model);
            }
        } elseif ($model instanceof Collection && ($model->wasChanged(['name', 'parent_id', 'is_archived', 'is_pinned', 'folder_type']))) {
            $tracker->record($model);
        } elseif ($model instanceof OrganizationAlias && ($model->wasChanged(['kind', 'alias_key', 'canonical_name']))) {
            $tracker->record($model);
        } elseif ($model instanceof UserPreference && ($model->wasChanged(['auto_organize_documents', 'organization_naming_rules', 'organization_feedback_reset_at']))) {
            $tracker->record($model);
        }
    }

    public function deleted(Model $model): void
    {
        app(OrganizationRevisionService::class)->record($model);
    }

    public function restored(Model $model): void
    {
        app(OrganizationRevisionService::class)->record($model);
    }
}
