<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which routes Ziggy will include in the JavaScript routing
    | configuration. Sensitive internal routes are excluded by default.
    |
    */

    // Explicitly exclude sensitive/internal routes from being exposed to JavaScript
    'except' => [
        'horizon.*',           // Laravel Horizon dashboard routes
        'log-viewer.*',        // Log Viewer routes
        'sanctum.*',          // Sanctum CSRF routes
        '_ignition.*',        // Ignition debug routes
        'debugbar.*',         // Debugbar routes
    ],
];
