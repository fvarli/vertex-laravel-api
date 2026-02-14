<?php

$frontendOrigins = array_values(array_filter(array_map(
    static fn (string $value): string => trim($value),
    explode(',', (string) env('FRONTEND_URLS', (string) env('FRONTEND_URL', 'http://localhost:3000')))
)));

if ($frontendOrigins === []) {
    $frontendOrigins = ['http://localhost:3000'];
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
