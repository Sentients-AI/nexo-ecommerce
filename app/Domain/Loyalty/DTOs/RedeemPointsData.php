<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\DTOs;

use App\Shared\DTOs\BaseData;

final class RedeemPointsData extends BaseData
{
    public function __construct(
        public readonly int $userId,
        public readonly int $points,
        public readonly string $description,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
    ) {}
}
