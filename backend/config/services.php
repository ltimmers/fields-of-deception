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
        'provider' => env('LLM_PROVIDER', 'openai_compatible'),
        'base_url' => env('LLM_BASE_URL', 'http://localhost:1234/v1'),
        'model' => env('LLM_MODEL', 'local-model'),
        'enabled' => env('LLM_ENABLED', false),
        'api_key' => env('LLM_API_KEY'),
        'azure_endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'azure_api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
        'azure_deployment' => env('AZURE_OPENAI_DEPLOYMENT'),
    ],
];
