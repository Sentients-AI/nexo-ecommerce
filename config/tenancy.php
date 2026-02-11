<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | The base domain used for subdomain tenant resolution. Tenants are
    | identified by the subdomain part of the URL (e.g., tenant.example.com).
    |
    */
    'base_domain' => env('APP_BASE_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains that cannot be used as tenant slugs. These are reserved for
    | system use such as the main marketing site, admin panel, API, etc.
    |
    */
    'reserved_subdomains' => [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'ftp',
        'smtp',
        'imap',
        'pop',
        'blog',
        'help',
        'support',
        'docs',
        'status',
        'static',
        'assets',
        'cdn',
        'media',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Tenant Settings
    |--------------------------------------------------------------------------
    |
    | Default settings applied to new tenants. These can be overridden
    | per-tenant through the settings JSON column.
    |
    */
    'default_settings' => [
        'currency' => 'MYR',
        'timezone' => 'Asia/Kuala_Lumpur',
        'tax_rate' => 0,
        'date_format' => 'Y-m-d',
        'locale' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Period
    |--------------------------------------------------------------------------
    |
    | The number of days for the trial period when a new tenant is created.
    | Set to null to disable trial periods.
    |
    */
    'trial_days' => 14,
];
