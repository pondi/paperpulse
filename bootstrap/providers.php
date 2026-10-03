<?php

use App\Providers\AppServiceProvider;
use App\Providers\JobServiceProvider;
use App\Providers\OrganizationServiceProvider;
use App\Providers\ServiceLayerServiceProvider;

return [
    AppServiceProvider::class,
    JobServiceProvider::class,
    OrganizationServiceProvider::class,
    ServiceLayerServiceProvider::class,
];
