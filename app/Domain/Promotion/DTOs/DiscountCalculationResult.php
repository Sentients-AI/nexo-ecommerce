<?php

declare(strict_types=1);

namespace App\Domain\Promotion\DTOs;

use App\Shared\DTOs\BaseData;

final class DiscountCalculationResult extends BaseData
{
    /**
     * @param  array<int>  $eligibleItemIds
     */
    public function __construct(
        public int $discountCents,
        public int $eligibleSubtotalCents,
        public ?int $promotionId,
        public array $eligibleItemIds = [],
    ) {}

    public static function zero(): self
    {
        return new self(
            discountCents: 0,
            eligibleSubtotalCents: 0,
            promotionId: null,
            eligibleItemIds: [],
        );
    }
}
