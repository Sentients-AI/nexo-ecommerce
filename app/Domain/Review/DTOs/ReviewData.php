<?php

declare(strict_types=1);

namespace App\Domain\Review\DTOs;

final readonly class ReviewData
{
    public function __construct(
        public int $productId,
        public int $userId,
        public int $rating,
        public string $title,
        public string $body,
    ) {}
}
