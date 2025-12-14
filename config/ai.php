<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use for processing tasks. Currently supports
    | OpenAI for text processing and analysis tasks.
    |
    */
    'provider' => 'openai',

    /*
    |--------------------------------------------------------------------------
    | AI Service Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for AI service providers including API keys, timeouts,
    | and model settings for different task types.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'timeout' => env('OPENAI_REQUEST_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Task-Specific Models
    |--------------------------------------------------------------------------
    |
    | Model selection for specific AI processing tasks. Each task type
    | can use a different model optimized for that particular use case.
    |
    */
    'models' => [
        'receipt' => 'gpt-4o-mini',
        'document' => 'gpt-4o',
        'summary' => 'gpt-4o-mini',
        'classification' => 'gpt-4o-mini',
        'entities' => 'gpt-4o-mini',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Options
    |--------------------------------------------------------------------------
    |
    | Fine-tuned parameters for different AI processing tasks including
    | token limits, temperature settings, and timeout values.
    |
    */
    'options' => [
        'max_tokens' => [
            'receipt' => 1024,
            'document' => 2048,
            'summary' => 200,
        ],
        'temperature' => [
            'receipt' => 0.1,
            'document' => 0.2,
            'summary' => 0.3,
        ],
        // Controls OpenAI JSON schema strict mode; false avoids provider
        // rejections when optional fields are omitted by the model
        'strict_json_schema' => env('AI_STRICT_JSON_SCHEMA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration
    |--------------------------------------------------------------------------
    |
    | Optical Character Recognition settings for text extraction from images
    | and documents. Supports Textract as the primary provider.
    |
    */
    'ocr' => [
        'provider' => 'textract',

        'providers' => [
            'textract' => [
                'region' => env('TEXTRACT_REGION', 'eu-central-1'),
                'key' => env('TEXTRACT_KEY'),
                'secret' => env('TEXTRACT_SECRET'),
                'bucket' => env('TEXTRACT_BUCKET'),
                'timeout' => env('TEXTRACT_TIMEOUT', 120),
                'polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10),
            ],
        ],

        'options' => [
            'cache_results' => true,
            'cache_duration' => 7, // days
            'min_confidence' => 0.8,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Smart token limiting for document analysis to reduce OpenAI costs.
    | Large documents are truncated to beginning + end sections.
    |
    */
    'document_analysis' => [
        // Max characters to send to AI for analysis (~4000 tokens)
        // Prevents expensive API calls for very large documents
        'max_chars' => env('AI_DOCUMENT_MAX_CHARS', 16000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits and processing constraints for AI operations to prevent
    | abuse and manage resource usage effectively.
    |
    */
    'limits' => [
        'max_requests_per_minute' => env('AI_RATE_LIMIT', 60),
        'max_file_size_mb' => env('AI_MAX_FILE_SIZE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Caching settings for AI processing results to improve performance
    | and reduce API costs for repeated operations.
    |
    */
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'ai_cache_',
    ],
];
