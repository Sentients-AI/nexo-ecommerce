<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Annual => 'Annual',
        };
    }

    public function stripeInterval(): string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Annual => 'year',
        };
    }
}
