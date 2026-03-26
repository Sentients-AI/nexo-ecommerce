<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Enums;

enum DiscountType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Bogo = 'bogo';
    case Tiered = 'tiered';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Amount',
            self::Percentage => 'Percentage',
            self::Bogo => 'Buy X Get Y Free (BOGO)',
            self::Tiered => 'Tiered (Spend More, Save More)',
        };
    }
}
