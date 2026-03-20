<?php

declare(strict_types=1);

namespace App\Domain\Referral\Exceptions;

use RuntimeException;

final class SelfReferralException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You cannot use your own referral code.');
    }
}
