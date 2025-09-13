<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Beta Features
    |--------------------------------------------------------------------------
    |
    | Control which beta/experimental features are enabled. These features
    | are only shown when the application is in development mode or when
    | explicitly enabled via environment variables.
    |
    */

    'beta' => [
        // Enable beta features based on app environment
        'enabled' => env('ENABLE_BETA_FEATURES', app()->environment('local', 'development')),

        // Individual feature flags
        'documents' => env('BETA_DOCUMENTS', env('ENABLE_BETA_FEATURES', app()->environment('local', 'development'))),
    ],
];
