<?php

declare(strict_types=1);

return [
    'default_referrer_reward_points' => env('REFERRAL_REFERRER_REWARD_POINTS', 500),
    'default_referee_discount_percent' => env('REFERRAL_REFEREE_DISCOUNT_PERCENT', 10),
    'default_max_uses' => env('REFERRAL_DEFAULT_MAX_USES', 10),
    'default_validity_days' => env('REFERRAL_DEFAULT_VALIDITY_DAYS', 30),
];
