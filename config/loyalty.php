<?php

declare(strict_types=1);

return [
    'points_per_dollar' => env('LOYALTY_POINTS_PER_DOLLAR', 1),
    'points_value_cents' => env('LOYALTY_POINTS_VALUE_CENTS', 1),
    'minimum_redemption' => env('LOYALTY_MINIMUM_REDEMPTION', 100),
];
