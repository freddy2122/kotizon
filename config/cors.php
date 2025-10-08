<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Allow all by default in local; set CORS_ALLOW_ORIGINS to a comma-separated list for stricter control
    'allowed_origins' => explode(',', env('CORS_ALLOW_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
