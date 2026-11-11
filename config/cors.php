<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Browsers may only call the API from the origins listed in
| CORS_ALLOWED_ORIGINS (comma-separated, scheme + host + optional port,
| e.g. "https://app.example.com,http://localhost:5173"). Empty means no
| cross-origin browser access at all. Server-to-server clients (curl,
| backends) are not affected by CORS.
|
| Auth is bearer tokens only, so credentials stay off. If cookie / SPA auth
| is ever enabled, never combine it with a wildcard origin.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    // Retry-After is sent with 202 while an invoice PDF is rendering.
    'exposed_headers' => ['Retry-After'],

    'max_age' => 600,

    'supports_credentials' => false,

];
