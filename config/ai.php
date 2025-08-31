<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */
    'provider' => 'openai',

    /*
    |--------------------------------------------------------------------------
    | Model Selection Strategy
    |--------------------------------------------------------------------------
    */
    'model_selection' => [
        'strategy' => 'optimal', // optimal, fixed, cost_efficient
        'auto_optimize' => true,
        'cache_duration' => 3600, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Models Configuration - Latest 2025 Models
    |--------------------------------------------------------------------------
    */
    'models' => [
        // Default models per task
        'receipt' => 'auto', // 'auto' = use optimal selection
        'document' => 'auto',
        'summary' => 'auto',
        'classification' => 'auto',
        'entity_extraction' => 'auto',
        'general' => 'auto', // Add general task support

        // Provider-specific overrides
        'openai' => [
            'receipt' => 'gpt-4o-mini',
            'document' => 'gpt-4o',
            'general' => 'gpt-4o-mini',
            'premium' => 'gpt-4o',
        ],

        'anthropic' => [
            'receipt' => 'claude-3-5-sonnet-20241022',
            'document' => 'claude-3-5-sonnet-20241022',
            'general' => 'claude-3-haiku-20240307',
            'premium' => 'claude-3-5-sonnet-20241022',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Performance Tracking
    |--------------------------------------------------------------------------
    */
    'performance_tracking' => [
        'enabled' => true,
        'metrics_retention_days' => 30,
        'auto_adjust_models' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Management
    |--------------------------------------------------------------------------
    */
    'cost_management' => [
        'budget_alerts' => true,
        'daily_budget_limit' => 10.0, // USD
        'monthly_budget_limit' => 200.0, // USD
        'cost_optimization' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Issues and Maintenance
    |--------------------------------------------------------------------------
    */
    'model_issues' => [
        // Track known issues with specific models
        // 'gpt-4.1' => [
        //     ['severity' => 'minor', 'description' => 'Occasional timeout on large requests']
        // ]
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
        'max_retries' => 3,
        'retry_delay' => 1000, // milliseconds
        'timeout' => 60, // seconds
        'max_tokens' => [
            'receipt' => 1024,
            'document' => 2048,
            'summary' => 200,
        ],
        'temperature' => [
            'receipt' => 0.1,
            'document' => 0.2,
            'creative' => 0.7,
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
    'fallback_providers' => 'anthropic,openai',

    /*
    |--------------------------------------------------------------------------
    | Validation Options
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'enabled' => true,
        'strict_mode' => false,
        'log_warnings' => true,
        'sanitize_data' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 3,
        'base_delay' => 1000, // milliseconds
        'max_delay' => 30000, // milliseconds
        'backoff_multiplier' => 2,
        'jitter' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific AI features.
    |
    */
    'features' => [
        'auto_categorization' => true,
        'auto_tagging' => true,
        'entity_extraction' => true,
        'summary_generation' => true,
        'merchant_matching' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling and Resilience
    |--------------------------------------------------------------------------
    */
    'error_handling' => [
        'enable_circuit_breaker' => true,
        'enable_fallbacks' => true,
        'enable_graceful_degradation' => true,
    ],

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'timeout' => 300, // seconds
        'openai_timeout' => 300,
        'anthropic_timeout' => 300,
    ],

    'fallback_chains' => [
        'receipt' => explode(',', 'openai,anthropic'),
        'document' => explode(',', 'anthropic,openai'),
        'summary' => explode(',', 'openai,anthropic'),
        'classification' => explode(',', 'openai,anthropic'),
        'entities' => explode(',', 'anthropic,openai'),
        'tags' => explode(',', 'openai,anthropic'),
        'general' => explode(',', 'openai,anthropic'),
    ],

    'health_monitoring' => [
        'enabled' => true,
        'check_interval' => 300, // seconds
        'thresholds' => [
            'error_rate_threshold' => 0.2,
            'response_time_threshold' => 30000,
            'availability_threshold' => 0.95,
        ],
        'alert_channels' => explode(',', 'log,notification'),
    ],

    'graceful_degradation' => [
        'enabled' => true,
        'use_basic_analysis' => true,
        'pattern_matching' => true,
    ],
];
