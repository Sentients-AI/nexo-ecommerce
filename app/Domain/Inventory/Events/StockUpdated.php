<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Events;

use App\Shared\Domain\DomainEvent;

final class StockUpdated extends DomainEvent
{
    public function __construct(
        public readonly int $productId,
        public readonly int $stockId,
        public readonly ?int $tenantId,
        public readonly int $quantityAvailable,
        public readonly int $quantityReserved,
        public readonly string $changeType,
    ) {
        parent::__construct();
    }
}
