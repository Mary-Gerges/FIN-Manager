<?php
// config/cors.php — Allow frontend origins

return [
    'paths'                    => ['api/*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://localhost:3000',    // React dev
        'http://localhost:5173',    // Vite dev
        'http://localhost:8080',    // Vue dev
        env('FRONTEND_URL', '*'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['Content-Disposition'],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];
