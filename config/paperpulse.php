<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for various operations in the application.
    |
    */

    'rate_limits' => [
        // Maximum file uploads per minute per user
        'file_uploads' => env('RATE_LIMIT_FILE_UPLOADS', 10),
        
        // Maximum PulseDav auth attempts per minute per IP
        'pulsedav_auth' => env('RATE_LIMIT_PULSEDAV_AUTH', 10),
        
        // Maximum API requests per minute per user
        'api_requests' => env('RATE_LIMIT_API_REQUESTS', 60),
        
        // Maximum export requests per hour per user
        'export_requests' => env('RATE_LIMIT_EXPORT_REQUESTS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure file processing settings for the application.
    |
    */

    'file_processing' => [
        // Maximum file size for uploads (in KB)
        'max_file_size' => env('MAX_FILE_SIZE', 2048),
        
        // Supported file types
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/jpg',
            'image/gif',
            'image/svg+xml',
            'application/pdf',
        ],
        
        // Supported extensions
        'allowed_extensions' => ['jpeg', 'png', 'jpg', 'gif', 'svg', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure job processing settings for the application.
    |
    */

    'jobs' => [
        // Job timeout in seconds
        'timeout' => env('JOB_TIMEOUT', 3600),
        
        // Number of retries
        'retries' => env('JOB_RETRIES', 5),
        
        // Backoff time in seconds
        'backoff' => env('JOB_BACKOFF', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure analytics settings for the application.
    |
    */

    'analytics' => [
        // Default period for analytics views
        'default_period' => env('ANALYTICS_DEFAULT_PERIOD', 'month'),
        
        // Maximum items to show in top lists
        'top_items_limit' => env('ANALYTICS_TOP_ITEMS_LIMIT', 10),
    ],

];