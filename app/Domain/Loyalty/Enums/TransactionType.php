<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Enums;

enum TransactionType: string
{
    case Earned = 'earned';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Adjustment = 'adjustment';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Earned => 'Points Earned',
            self::Redeemed => 'Points Redeemed',
            self::Expired => 'Points Expired',
            self::Adjustment => 'Manual Adjustment',
            self::Refunded => 'Points Refunded',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Earned, self::Refunded => true,
            self::Redeemed, self::Expired, self::Adjustment => false,
        };
    }
}
