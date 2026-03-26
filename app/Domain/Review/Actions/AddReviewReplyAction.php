<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\DTOs\StoreReviewReplyData;
use App\Domain\Review\Models\ReviewReply;

final class AddReviewReplyAction
{
    public function execute(StoreReviewReplyData $data): ReviewReply
    {
        return ReviewReply::create([
            'review_id' => $data->reviewId,
            'user_id' => $data->userId,
            'body' => $data->body,
            'is_merchant_reply' => $data->isMerchantReply,
        ]);
    }
}
