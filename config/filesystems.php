<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'textract' => [
            'driver' => 's3',
            'key' => env('TEXTRACT_KEY'),
            'secret' => env('TEXTRACT_SECRET'),
            'region' => env('TEXTRACT_REGION'),
            'bucket' => env('TEXTRACT_BUCKET'),
            'throw' => true,
        ],

        'documents' => [
            'driver' => env('DOCUMENTS_STORAGE_DRIVER', 'local'),
            'root' => env('DOCUMENTS_STORAGE_ROOT', storage_path('app/documents')),
            'url' => env('DOCUMENTS_AWS_URL'),
            'endpoint' => env('DOCUMENTS_AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('DOCUMENTS_AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'visibility' => 'private',
            'key' => env('DOCUMENTS_AWS_ACCESS_KEY_ID'),
            'secret' => env('DOCUMENTS_AWS_SECRET_ACCESS_KEY'),
            'region' => env('DOCUMENTS_AWS_DEFAULT_REGION'),
            'bucket' => env('DOCUMENTS_AWS_BUCKET'),
        ],
        
        // Incoming bucket for PulseDav uploads
        's3-incoming' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_INCOMING_BUCKET'),
            'visibility' => 'private',
            'throw' => true,
        ],
        
        // Storage bucket for permanent storage
        's3-storage' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_STORAGE_BUCKET'),
            'visibility' => 'private',
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | S3 Bucket Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for dual S3 bucket setup for document processing
    |
    */
    
    'incoming_prefix' => env('AWS_S3_INCOMING_PREFIX', 'incoming/'),

];
