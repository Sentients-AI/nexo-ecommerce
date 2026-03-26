<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;

final class ApproveReviewAction
{
    public function execute(Review $review): Review
    {
        $review->update(['is_approved' => true]);

        return $review;
    }
}
