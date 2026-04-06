<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum EarningStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case PaidOut = 'paid_out';
}
