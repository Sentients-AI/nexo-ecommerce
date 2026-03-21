<?php

declare(strict_types=1);

namespace App\Domain\Referral\Exceptions;

use RuntimeException;

final class ReferralCodeExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This referral code has expired.');
    }
}
