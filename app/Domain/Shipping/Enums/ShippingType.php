<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Enums;

enum ShippingType: string
{
    case FlatRate = 'flat_rate';
    case Free = 'free';
    case FreeOverAmount = 'free_over_amount';

    public function label(): string
    {
        return match ($this) {
            self::FlatRate => 'Flat Rate',
            self::Free => 'Free Shipping',
            self::FreeOverAmount => 'Free Over Amount',
        };
    }
}
