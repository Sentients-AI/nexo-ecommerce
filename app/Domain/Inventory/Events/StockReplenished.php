<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Events;

use App\Shared\Domain\DomainEvent;

final class StockReplenished extends DomainEvent
{
    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId,
        public readonly int $newQuantity,
    ) {
        parent::__construct();
    }
}
