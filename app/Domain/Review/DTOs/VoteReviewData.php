<?php

declare(strict_types=1);

namespace App\Domain\Review\DTOs;

final readonly class VoteReviewData
{
    public function __construct(
        public int $reviewId,
        public int $userId,
        public bool $isHelpful,
    ) {}
}
