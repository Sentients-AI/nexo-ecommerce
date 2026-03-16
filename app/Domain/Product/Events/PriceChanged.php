<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Shared\Domain\DomainEvent;

final class PriceChanged extends DomainEvent
{
    public function __construct(
        public readonly int $productId,
        public readonly ?int $tenantId,
        public readonly int $newPriceCents,
        public readonly ?int $newSalePrice,
    ) {
        parent::__construct();
    }
}
