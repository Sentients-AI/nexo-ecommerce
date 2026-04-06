<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Products with quantity_available at or below this number are considered
    | low stock. Used for dashboard warnings and vendor email alerts.
    |
    */

    'low_stock_threshold' => 5,

    /*
    |--------------------------------------------------------------------------
    | Low Stock Notifications
    |--------------------------------------------------------------------------
    |
    | When enabled, tenant admin users receive an email alert when a product's
    | stock falls at or below the threshold for the first time.
    |
    */

    'low_stock_notifications' => true,
];
