<?php

declare(strict_types=1);

namespace App\Domain\Referral\DTOs;

use App\Shared\DTOs\BaseData;
use Carbon\CarbonInterface;

final class GenerateReferralCodeData extends BaseData
{
    public function __construct(
        public readonly int $userId,
        public readonly int $referrerRewardPoints,
        public readonly int $refereeDiscountPercent,
        public readonly ?int $maxUses,
        public readonly ?CarbonInterface $expiresAt,
    ) {}
}
