<?php

declare(strict_types=1);

namespace App\Domain\Referral\Events;

use App\Shared\Domain\DomainEvent;

final class ReferralCodeUsed extends DomainEvent
{
    public function __construct(
        public readonly int $referralCodeId,
        public readonly int $referrerUserId,
        public readonly int $refereeUserId,
        public readonly int $pointsAwarded,
        public readonly int $discountPercent,
    ) {
        parent::__construct();
    }
}
