<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;

final class RejectReviewAction
{
    public function execute(Review $review): Review
    {
        $review->update(['is_approved' => false]);

        return $review;
    }
}
