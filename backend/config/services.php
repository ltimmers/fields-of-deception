<?php

return [
    'expiry' => env('SERVICE_CACHE_EXPIRY', 10080),

    /*
    |--------------------------------------------------------------------------
    | LLM Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the local LLM service used for AI moves.
    |
    */
    'llm' => [
        'base_url' => env('LLM_BASE_URL', 'http://localhost:1234/v1'),
        'model' => env('LLM_MODEL', 'local-model'),
        'enabled' => env('LLM_ENABLED', false),
    ],
];
