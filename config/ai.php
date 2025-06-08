<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | application. You may set this to any of the providers defined below.
    |
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Models Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI models used for different tasks.
    | Different models may be optimized for different types of analysis.
    |
    */
    'models' => [
        // OpenAI models
        'receipt' => env('AI_MODEL_RECEIPT', 'gpt-3.5-turbo'),
        'document' => env('AI_MODEL_DOCUMENT', 'gpt-4'),
        
        // Anthropic models
        'anthropic_receipt' => env('AI_MODEL_ANTHROPIC_RECEIPT', 'claude-3-haiku-20240307'),
        'anthropic_document' => env('AI_MODEL_ANTHROPIC_DOCUMENT', 'claude-3-sonnet-20240229'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Service Options
    |--------------------------------------------------------------------------
    |
    | Various options for AI service behavior.
    |
    */
    'options' => [
        'max_retries' => env('AI_MAX_RETRIES', 3),
        'retry_delay' => env('AI_RETRY_DELAY', 1000), // milliseconds
        'timeout' => env('AI_TIMEOUT', 60), // seconds
        'max_tokens' => [
            'receipt' => env('AI_MAX_TOKENS_RECEIPT', 1024),
            'document' => env('AI_MAX_TOKENS_DOCUMENT', 2048),
            'summary' => env('AI_MAX_TOKENS_SUMMARY', 200),
        ],
        'temperature' => [
            'receipt' => env('AI_TEMPERATURE_RECEIPT', 0.1),
            'document' => env('AI_TEMPERATURE_DOCUMENT', 0.2),
            'creative' => env('AI_TEMPERATURE_CREATIVE', 0.7),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Providers
    |--------------------------------------------------------------------------
    |
    | When the primary provider fails, these providers will be tried in order.
    |
    */
    'fallback_providers' => env('AI_FALLBACK_PROVIDERS', 'anthropic,openai'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific AI features.
    |
    */
    'features' => [
        'auto_categorization' => env('AI_AUTO_CATEGORIZATION', true),
        'auto_tagging' => env('AI_AUTO_TAGGING', true),
        'entity_extraction' => env('AI_ENTITY_EXTRACTION', true),
        'summary_generation' => env('AI_SUMMARY_GENERATION', true),
        'merchant_matching' => env('AI_MERCHANT_MATCHING', true),
    ],
];