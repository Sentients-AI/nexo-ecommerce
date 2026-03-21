<?php

declare(strict_types=1);

namespace App\Domain\Referral\DTOs;

use App\Shared\DTOs\BaseData;

final class ApplyReferralCodeData extends BaseData
{
    public function __construct(
        public readonly string $code,
        public readonly int $refereeUserId,
    ) {}
}
