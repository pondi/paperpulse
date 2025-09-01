<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'textract' => [
        'key' => env('TEXTRACT_KEY'),
        'secret' => env('TEXTRACT_SECRET'),
        'region' => env('TEXTRACT_REGION', 'us-east-1'),
        'version' => env('TEXTRACT_VERSION', '2018-06-27'),
        'timeout' => env('TEXTRACT_TIMEOUT', 120),
        'polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pulsedav' => [
        'auth_enabled' => env('PULSEDAV_AUTH_ENABLED', true),
        's3_incoming_prefix' => env('S3_INCOMING_PREFIX', 'incoming/'),
    ],

];
