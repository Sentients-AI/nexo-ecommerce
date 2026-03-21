<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Exceptions;

use RuntimeException;

final class InsufficientPointsException extends RuntimeException
{
    public function __construct(int $available, int $requested)
    {
        parent::__construct("Insufficient loyalty points. Available: {$available}, Requested: {$requested}");
    }
}
