<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Specifications;

use App\Domain\Promotion\Models\Promotion;
use App\Shared\Specifications\AbstractSpecification;
use Carbon\Carbon;

/**
 * @extends AbstractSpecification<Promotion>
 */
final class PromotionIsValid extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof Promotion) {
            $this->setFailureReason('Candidate must be a Promotion instance');

            return false;
        }

        if (! $candidate->is_active) {
            $this->setFailureReason('Promotion is not active');

            return false;
        }

        $now = Carbon::now();

        if ($now->lt($candidate->starts_at)) {
            $this->setFailureReason('Promotion has not started yet');

            return false;
        }

        if ($now->gt($candidate->ends_at)) {
            $this->setFailureReason('Promotion has expired');

            return false;
        }

        if ($candidate->usage_limit !== null && $candidate->usage_count >= $candidate->usage_limit) {
            $this->setFailureReason('Promotion usage limit has been reached');

            return false;
        }

        return true;
    }
}
