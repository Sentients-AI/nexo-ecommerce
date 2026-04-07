<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | These thresholds control when orders and users are flagged as potentially
    | fraudulent. All monetary values are in cents.
    |
    */

    'high_value_threshold_cents' => (int) env('FRAUD_HIGH_VALUE_THRESHOLD_CENTS', 50000),

    'new_user_days' => (int) env('FRAUD_NEW_USER_DAYS', 7),

    'payment_attempts_threshold' => (int) env('FRAUD_PAYMENT_ATTEMPTS_THRESHOLD', 3),

    'velocity_orders' => (int) env('FRAUD_VELOCITY_ORDERS', 3),

    'velocity_window_hours' => (int) env('FRAUD_VELOCITY_WINDOW_HOURS', 1),

    'high_refund_rate' => (float) env('FRAUD_HIGH_REFUND_RATE', 0.5),

    'lookback_days' => (int) env('FRAUD_LOOKBACK_DAYS', 7),
];
