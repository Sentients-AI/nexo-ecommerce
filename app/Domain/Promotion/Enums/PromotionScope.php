<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Enums;

enum PromotionScope: string
{
    case All = 'all';
    case Product = 'product';
    case Category = 'category';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Products',
            self::Product => 'Specific Products',
            self::Category => 'Specific Categories',
        };
    }
}
