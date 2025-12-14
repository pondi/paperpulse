<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Processing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for document and receipt processing,
    | including supported formats, file size limits, and conversion settings.
    |
    */

    'documents' => [
        /*
        | Supported file formats by type
        */
        'supported_formats' => [
            'receipts' => explode(',', env('SUPPORTED_RECEIPT_FORMATS', 'jpg,jpeg,png,pdf,tiff,tif')),
            'documents' => explode(',', env('SUPPORTED_DOCUMENT_FORMATS',
                'doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,pdf,rtf,txt,html')),
        ],

        /*
        | Office document formats that require conversion to PDF
        */
        'office_formats' => [
            'word' => ['doc', 'docx', 'odt', 'rtf'],
            'spreadsheet' => ['xls', 'xlsx', 'ods'],
            'presentation' => ['ppt', 'pptx', 'odp'],
            'other' => ['txt', 'html'],
        ],

        /*
        | Maximum file size in MB
        */
        'max_file_size' => [
            'receipts' => (int) env('MAX_RECEIPT_FILE_SIZE', 100),  // MB
            'documents' => (int) env('MAX_DOCUMENT_FILE_SIZE', 100), // MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Office Document Conversion Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for converting office documents (.docx, .xlsx, etc.) to PDF/A
    | format using Gotenberg and Redis job queue.
    |
    */

    'conversion' => [
        /*
        | Enable/disable office document conversion
        */
        'enabled' => env('OFFICE_CONVERSION_ENABLED', true),

        /*
        | Gotenberg service URL
        */
        'service_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

        /*
        | Conversion timeout in seconds
        */
        'timeout' => (int) env('CONVERSION_TIMEOUT', 120),

        /*
        | Maximum number of retry attempts for failed conversions
        */
        'max_retries' => (int) env('CONVERSION_MAX_RETRIES', 3),

        /*
        | Polling interval in seconds (how often to check conversion status)
        */
        'polling_interval' => (float) env('CONVERSION_POLLING_INTERVAL', 1),

        /*
        | Redis queue name for pending conversions
        */
        'redis_queue' => env('CONVERSION_REDIS_QUEUE', 'conversion:pending'),

        /*
        | Redis queue name for processing conversions
        */
        'redis_processing_queue' => env('CONVERSION_PROCESSING_QUEUE', 'conversion:processing'),

        /*
        | Redis queue name for failed conversions
        */
        'redis_failed_queue' => env('CONVERSION_FAILED_QUEUE', 'conversion:failed'),
    ],
];
