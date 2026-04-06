<?php

declare(strict_types=1);

return [
    /*
     * Platform fee rate applied to each order's subtotal.
     * e.g. 0.02 = 2%
     */
    'platform_fee_rate' => (float) env('PLATFORM_FEE_RATE', 0.02),

    /*
     * Number of days before earnings are marked as available for payout.
     */
    'payout_hold_days' => (int) env('PAYOUT_HOLD_DAYS', 7),
];
