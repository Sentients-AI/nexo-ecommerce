<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Events;

use App\Shared\Domain\DomainEvent;

final class PointsRedeemed extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $points,
        public readonly int $newBalance,
    ) {
        parent::__construct();
    }
}
