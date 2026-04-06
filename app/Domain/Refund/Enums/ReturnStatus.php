<?php

declare(strict_types=1);

namespace App\Domain\Refund\Enums;

enum ReturnStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed], true);
    }

    public function canBeReviewed(): bool
    {
        return $this === self::Pending;
    }
}
