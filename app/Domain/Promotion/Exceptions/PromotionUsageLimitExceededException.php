<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Exceptions;

use DomainException;

final class PromotionUsageLimitExceededException extends DomainException
{
    public function __construct(
        public readonly int $promotionId,
        public readonly int $userId,
        public readonly int $currentUsageCount,
        public readonly int $limit,
    ) {
        parent::__construct(
            "User {$userId} has exceeded usage limit for promotion {$promotionId}. Current: {$currentUsageCount}, Limit: {$limit}"
        );
    }
}
