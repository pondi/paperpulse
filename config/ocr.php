<?php

return [
    // Simplified: single OCR provider (Textract only)
    'provider' => 'textract',

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
    // Removed Tesseract config from core to reduce surface area

    /*
    |--------------------------------------------------------------------------
    | OCR Options
    |--------------------------------------------------------------------------
    |
    | Various options for OCR processing behavior.
    |
    */
    'options' => [
        'cache_results' => true,
        'cache_duration' => 7, // days
        'min_confidence' => 0.8,
    ],
];
