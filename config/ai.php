<?php

return [
    // Keep it simple: single provider and fixed models per task
    'provider' => 'openai',

    'models' => [
        'receipt' => 'gpt-4o-mini',
        'document' => 'gpt-4o',
        'summary' => 'gpt-4o-mini',
        'classification' => 'gpt-4o-mini',
        'entities' => 'gpt-4o-mini',
    ],

    // Basic per-task parameters (tuned, no env sprawl)
    'options' => [
        'timeout' => 60,
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
    ],
];
