<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

final class PaymentAlreadyProcessedException extends DomainException
{
    public function __construct(
        string $message = 'This payment has already been processed.'
    ) {
        parent::__construct($message);
    }
}
