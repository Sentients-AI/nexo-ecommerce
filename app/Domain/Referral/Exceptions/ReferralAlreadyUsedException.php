<?php

declare(strict_types=1);

namespace App\Domain\Referral\Exceptions;

use RuntimeException;

final class ReferralAlreadyUsedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You have already used this referral code.');
    }
}
