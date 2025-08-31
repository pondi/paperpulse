<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default OCR Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default OCR provider that will be used by the
    | application. You may set this to any of the providers defined below.
    |
    */
    'provider' => env('OCR_PROVIDER', 'textract'),

    /*
    |--------------------------------------------------------------------------
    | OCR Provider Fallback Order
    |--------------------------------------------------------------------------
    |
    | When the primary provider fails, these providers will be tried in order.
    |
    */
    'fallback_providers' => env('OCR_FALLBACK_PROVIDERS', 'tesseract'),

    /*
    |--------------------------------------------------------------------------
    | Textract Configuration
    |--------------------------------------------------------------------------
    |
    | AWS Textract specific configuration options.
    |
    */
    'textract' => [
        'region' => env('TEXTRACT_REGION', 'eu-central-1'),
        'key' => env('TEXTRACT_KEY'),
        'secret' => env('TEXTRACT_SECRET'),
        'bucket' => env('TEXTRACT_BUCKET'),
        'timeout' => env('TEXTRACT_TIMEOUT', 120),
        'polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract Configuration
    |--------------------------------------------------------------------------
    |
    | Tesseract OCR service configuration options.
    |
    */
    'tesseract' => [
        'endpoint' => env('TESSERACT_ENDPOINT', 'http://tesseract-service:8080'),
        'api_key' => env('TESSERACT_API_KEY'),
        'timeout' => env('TESSERACT_TIMEOUT', 60),
        'language' => env('TESSERACT_LANGUAGE', 'nor+eng'),
        'psm' => env('TESSERACT_PSM', 3), // Page Segmentation Mode
        'oem' => env('TESSERACT_OEM', 3), // OCR Engine Mode
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Options
    |--------------------------------------------------------------------------
    |
    | Various options for OCR processing behavior.
    |
    */
    'options' => [
        'cache_results' => env('OCR_CACHE_RESULTS', true),
        'cache_duration' => env('OCR_CACHE_DURATION', 7), // days
        'retry_attempts' => env('OCR_RETRY_ATTEMPTS', 2),
        'retry_delay' => env('OCR_RETRY_DELAY', 1000), // milliseconds
        'min_confidence' => env('OCR_MIN_CONFIDENCE', 0.8),
    ],
];
