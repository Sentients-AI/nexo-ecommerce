<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Specifications;

use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsage;
use App\Domain\User\Models\User;
use App\Shared\Specifications\AbstractSpecification;

/**
 * Checks if a user can use a promotion based on per-user limits.
 *
 * @extends AbstractSpecification<User>
 */
final class UserCanUsePromotion extends AbstractSpecification
{
    public function __construct(
        private readonly Promotion $promotion,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof User) {
            $this->setFailureReason('Candidate must be a User instance');

            return false;
        }

        // If no per-user limit is set, user can always use the promotion
        if ($this->promotion->per_user_limit === null) {
            return true;
        }

        // Count how many times this user has used this promotion
        $userUsageCount = PromotionUsage::query()
            ->where('promotion_id', $this->promotion->id)
            ->where('user_id', $candidate->id)
            ->count();

        if ($userUsageCount >= $this->promotion->per_user_limit) {
            $this->setFailureReason(
                "User has already used this promotion {$userUsageCount} time(s), limit is {$this->promotion->per_user_limit}"
            );

            return false;
        }

        return true;
    }
}
