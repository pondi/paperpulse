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
    'file_processing_provider' => env('FILE_PROCESSING_PROVIDER', 'textract+openai'),

    'provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

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
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3-flash-preview'),
            'timeout' => env('GEMINI_REQUEST_TIMEOUT', 90),
            'max_file_size_mb' => env('GEMINI_MAX_FILE_SIZE_MB', 50),
            'large_file_threshold_mb' => env('GEMINI_LARGE_FILE_THRESHOLD_MB', 15),
            'large_pdf_page_limit' => env('GEMINI_LARGE_PDF_PAGE_LIMIT', 25),
            'large_pdf_sample_size' => env('GEMINI_LARGE_PDF_SAMPLE_SIZE', 4),
            'text_max_bytes' => env('GEMINI_TEXT_MAX_BYTES', 200000),
            'supported_mime_types' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
                'text/plain',
                'text/csv',
                'text/html',
            ],
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
        'receipt' => 'gpt-5.2',
        'document' => 'gpt-5.2',
        'summary' => 'gpt-5.2',
        'classification' => 'gpt-5.2',
        'entities' => 'gpt-5.2',
        'merchant' => 'gpt-5.2',
        'fallback' => 'gpt-5.2',
        'default' => 'gpt-5.2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Options
    |--------------------------------------------------------------------------
    |
    | Fine-tuned parameters for different AI processing tasks including
    | token limits and timeout values.
    |
    */
    'options' => [
        'max_completion_tokens' => [
            // Generous limits to handle large/complex receipts without truncation
            // A 50-page receipt with many items needs ~8-16K tokens for complete extraction
            'receipt' => (int) env('AI_MAX_COMPLETION_TOKENS_RECEIPT', 16384),
            'document' => (int) env('AI_MAX_COMPLETION_TOKENS_DOCUMENT', 16384),
            'summary' => (int) env('AI_MAX_COMPLETION_TOKENS_SUMMARY', 1000),
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
        'provider' => env('OCR_PROVIDER', 'textract'),

        'providers' => [
            'textract' => [
                'region' => env('TEXTRACT_REGION', 'eu-central-1'),
                'key' => env('TEXTRACT_KEY'),
                'secret' => env('TEXTRACT_SECRET'),
                'bucket' => env('TEXTRACT_BUCKET'),
                'timeout' => env('TEXTRACT_TIMEOUT', 120),
                'polling_interval' => env('TEXTRACT_POLLING_INTERVAL', 10), // seconds between polls
                'max_polling_attempts' => env('TEXTRACT_MAX_POLLING_ATTEMPTS', 60), // max attempts (60 × 10s = 10 min)
            ],
        ],

        'options' => [
            'cache_results' => true,
            'cache_duration' => 7, // days
            'min_confidence' => 0.8,
            // Persisting raw Textract blocks can be extremely memory-heavy for some PDFs.
            // Keep disabled by default in production; enable temporarily for debugging/auditing.
            'store_blocks' => env('OCR_STORE_BLOCKS', false),
            // Upper bound for returning raw blocks in-memory (safety valve for Horizon workers).
            'max_blocks_in_memory' => (int) env('OCR_MAX_BLOCKS_IN_MEMORY', 5000),
            'pretty_print_structured' => env('OCR_PRETTY_PRINT_STRUCTURED', false),
            'pretty_print_blocks' => env('OCR_PRETTY_PRINT_BLOCKS', false),
            // When Textract rejects a PDF and we fall back to PDF->images, cap pages processed.
            'pdf_image_max_pages' => (int) env('OCR_PDF_IMAGE_MAX_PAGES', 3),
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
