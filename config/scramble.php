<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

// Only the settings this project changes. Scramble fills in every other
// top-level key from its own config file.
return [

    'api_path' => 'api',

    /*
    | Committed to the repo, so changes to the API show up in code review and
    | the Angular client (P5) can be generated from it. Regenerate with
    | `php artisan scramble:export`.
    */

    'export_path' => 'openapi.json',

    'info' => [
        'version' => env('API_VERSION', '0.1.0'),
        'description' => <<<'MD'
            Multi-tenant invoicing API for EU businesses: gap-free numbering per organisation, invoices frozen at issue, a VAT breakdown per rate, and PDFs rendered on a queue.

            Register or log in to get a token, then send it as `Authorization: Bearer <token>`. Money is integer cents; VAT rates and quantities are decimal strings.
            MD,
    ],

    'ui' => [
        'title' => 'eu-inv-api',
    ],

    // Routes behind auth:sanctum get the bearer scheme; register and login are
    // documented as public.
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,

];
