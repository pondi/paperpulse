<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\JobServiceProvider;
use App\Providers\OrganizationServiceProvider;
use App\Providers\ServiceLayerServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    JobServiceProvider::class,
    OrganizationServiceProvider::class,
    ServiceLayerServiceProvider::class,
];
