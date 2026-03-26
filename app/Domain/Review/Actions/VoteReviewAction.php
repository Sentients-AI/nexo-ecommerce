<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\DTOs\VoteReviewData;
use App\Domain\Review\Models\ReviewVote;

final class VoteReviewAction
{
    public function execute(VoteReviewData $data): ReviewVote
    {
        return ReviewVote::updateOrCreate(
            ['review_id' => $data->reviewId, 'user_id' => $data->userId],
            ['is_helpful' => $data->isHelpful],
        );
    }
}
