<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exceptions;

use RuntimeException;

final class GiftCardException extends RuntimeException
{
    public static function invalidCode(): self
    {
        return new self('Gift card code is invalid.');
    }

    public static function inactive(): self
    {
        return new self('Gift card is not active.');
    }

    public static function expired(): self
    {
        return new self('Gift card has expired.');
    }

    public static function noBalance(): self
    {
        return new self('Gift card has no remaining balance.');
    }
}
