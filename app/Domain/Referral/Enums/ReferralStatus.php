<?php

declare(strict_types=1);

namespace App\Domain\Referral\Enums;

enum ReferralStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Exhausted => 'Exhausted',
            self::Inactive => 'Inactive',
        };
    }
}
