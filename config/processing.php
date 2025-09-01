<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for document processing including supported file formats,
    | size limits, and processing options for different document types.
    |
    */
    'documents' => [
        'supported_formats' => [
            'receipts' => explode(',', env('SUPPORTED_RECEIPT_FORMATS', 'jpg,jpeg,png,gif,bmp,pdf')),
            'documents' => explode(',', env('SUPPORTED_DOCUMENT_FORMATS', 'doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,pdf,rtf')),
        ],

        'max_file_size' => [
            'receipts' => env('MAX_RECEIPT_SIZE', 10), // MB
            'documents' => env('MAX_DOCUMENT_SIZE', 50), // MB
        ],

        'processing' => [
            'extract_text' => env('DOCUMENT_EXTRACT_TEXT', true),
            'generate_thumbnails' => env('DOCUMENT_GENERATE_THUMBNAILS', true),
            'thumbnail_sizes' => [150, 300, 600],
        ],

        'storage' => [
            'disk' => env('DOCUMENT_STORAGE_DISK', 's3'),
            'retention_days' => env('DOCUMENT_RETENTION_DAYS', 0), // 0 = forever
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Specific settings for receipt analysis, validation, and merchant
    | matching functionality including confidence thresholds.
    |
    */
    'receipts' => [
        'analysis' => [
            'extract_line_items' => env('RECEIPT_EXTRACT_ITEMS', true),
            'match_merchants' => env('RECEIPT_MATCH_MERCHANTS', true),
            'auto_categorize' => env('RECEIPT_AUTO_CATEGORIZE', true),
            'confidence_threshold' => env('RECEIPT_CONFIDENCE_THRESHOLD', 0.85),
        ],

        'validation' => [
            'required_fields' => ['total', 'date'],
            'min_total_amount' => env('RECEIPT_MIN_TOTAL', 0.01),
            'max_total_amount' => env('RECEIPT_MAX_TOTAL', 999999.99),
        ],

        'merchant_matching' => [
            'fuzzy_threshold' => env('MERCHANT_FUZZY_THRESHOLD', 0.8),
            'auto_create_merchants' => env('AUTO_CREATE_MERCHANTS', true),
        ],

        'parsing' => [
            'use_forgiving_number_parser' => env('USE_FORGIVING_NUMBER_PARSER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Processing Jobs Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for background job processing including timeouts, retries,
    | and batch processing configuration for file operations.
    |
    */
    'jobs' => [
        'timeout' => env('PROCESSING_JOB_TIMEOUT', 300), // 5 minutes
        'max_retries' => env('PROCESSING_MAX_RETRIES', 3),
        'batch_size' => env('PROCESSING_BATCH_SIZE', 10),
        'backoff' => env('JOB_BACKOFF', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage and Temporary Files
    |--------------------------------------------------------------------------
    |
    | Configuration for temporary file handling during processing and
    | cleanup policies for intermediate files.
    |
    */
    'storage' => [
        'temp_disk' => env('TEMP_STORAGE_DISK', 'local'),
        'working_directory' => env('WORKING_DIRECTORY', 'temp/processing'),
        'cleanup_after_hours' => env('CLEANUP_AFTER_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Textract Integration
    |--------------------------------------------------------------------------
    |
    | AWS Textract specific configuration for OCR processing that was
    | previously in the receipt-scanner config file.
    |
    */
    'textract' => [
        'timeout' => env('TEXTRACT_TIMEOUT', 120),
        'polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10),
        'disk' => env('TEXTRACT_DISK'),
        'region' => env('TEXTRACT_REGION'),
        'version' => env('TEXTRACT_VERSION', '2018-06-27'),
        'key' => env('TEXTRACT_KEY'),
        'secret' => env('TEXTRACT_SECRET'),
    ],
];