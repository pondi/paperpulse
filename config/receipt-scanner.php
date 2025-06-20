<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Forgiving Number Parser
    |--------------------------------------------------------------------------
    |
    | This option determines if the application should try to parse numbers
    | that use non-standard decimal and thousand separators into a float.
    | For instance, it will attempt to correctly interpret "1,234.56" and "1.234,56".
    |
    */

    'use_forgiving_number_parser' => env('USE_FORGIVING_NUMBER_PARSER', true),

    /*
    |--------------------------------------------------------------------------
    | Textract Timeout
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum amount of time (in seconds) the application
    | should wait for the Textract process to complete before giving up.
    | It's especially useful for avoiding long-running processes.
    |
    */

    'textract_timeout' => env('TEXTRACT_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Textract Polling Interval
    |--------------------------------------------------------------------------
    |
    | This value sets the time interval (in seconds) at which the application
    | should check the status of a running Textract job.
    | This allows for a controlled and paced polling mechanism.
    |
    */

    'textract_polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10),

    /*
    |--------------------------------------------------------------------------
    | Textract Disk
    |--------------------------------------------------------------------------
    |
    | Specifies the storage disk to be used when uploading files for Textract.
    | Make sure the disk is properly configured in your filesystems configuration.
    |
    */

    'textract_disk' => env('TEXTRACT_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Textract Region, Version, Key, and Secret
    |--------------------------------------------------------------------------
    |
    | These settings are related to the AWS Textract configuration. They define
    | the region where Textract is being used, the version of the Textract API,
    | and the access key and secret key for authentication purposes.
    |
    */

    'textract_region' => env('TEXTRACT_REGION'),
    'textract_version' => env('TEXTRACT_VERSION', '2018-06-27'),
    'textract_key' => env('TEXTRACT_KEY'),
    'textract_secret' => env('TEXTRACT_SECRET'),

];