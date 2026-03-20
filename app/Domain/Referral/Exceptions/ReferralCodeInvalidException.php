<?php

declare(strict_types=1);

namespace App\Domain\Referral\Exceptions;

use RuntimeException;

final class ReferralCodeInvalidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The referral code is invalid or does not exist.');
    }
}
