<?php

declare(strict_types=1);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(
        ',',
        (string) env('FRONTEND_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'),
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for the Sanctum SPA cookie flow.
    'supports_credentials' => true,
];
