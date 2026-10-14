<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported countries
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 codes an organisation can be registered in. v1 ships
    | VAT rates for Bulgaria only, so adding a country means seeding its
    | rates first.
    |
    */

    'supported_countries' => ['BG'],

    /*
    |--------------------------------------------------------------------------
    | Time zone for invoice dates
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, but the issue date printed on an invoice
    | is the seller's local date: an invoice issued at 00:30 in Sofia is
    | dated that day, not the day before. One zone is enough while v1
    | supports one country; more countries need a per-organisation setting.
    |
    */

    'timezone' => 'Europe/Sofia',

];
