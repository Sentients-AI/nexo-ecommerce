<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | The list of currencies supported for display and checkout. Tenants may
    | configure any of these as their base currency.
    |
    */
    'supported' => ['USD', 'MYR', 'EUR', 'GBP', 'SGD', 'AUD', 'JPY', 'CAD'],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate API
    |--------------------------------------------------------------------------
    |
    | Frankfurter.app is a free, no-key-required exchange rate API backed by
    | European Central Bank data. Rates are updated daily.
    |
    */
    'api_url' => env('CURRENCY_API_URL', 'https://api.frankfurter.app'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache exchange rates. Defaults to 1 hour.
    |
    */
    'cache_ttl' => (int) env('CURRENCY_CACHE_TTL', 3600),
];
