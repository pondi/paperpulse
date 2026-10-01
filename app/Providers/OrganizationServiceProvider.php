<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;
use App\Models\UserPreference;
use App\Observers\OrganizationInputObserver;
use App\Services\OrganizationRevisionService;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OrganizationRevisionService::class);
    }

    public function boot(): void
    {
        foreach ([File::class, Collection::class, OrganizationAlias::class, UserPreference::class] as $model) {
            $model::observe(OrganizationInputObserver::class);
        }
    }
}
