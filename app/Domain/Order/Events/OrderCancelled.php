<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

use App\Shared\Domain\DomainEvent;

final class OrderCancelled extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly string $orderNumber,
    ) {
        parent::__construct();
    }
}
