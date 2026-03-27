<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Download Token Expiry
    |--------------------------------------------------------------------------
    | Number of hours before a download token expires. Customers must download
    | their files within this window after payment confirmation.
    */
    'expiry_hours' => (int) env('DOWNLOAD_EXPIRY_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Maximum Downloads Per Token
    |--------------------------------------------------------------------------
    | How many times a single download token can be used before it is exhausted.
    */
    'max_downloads' => (int) env('DOWNLOAD_MAX_DOWNLOADS', 5),
];
