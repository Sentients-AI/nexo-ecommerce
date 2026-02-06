<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Exceptions;

use DomainException;

final class PromotionNotApplicableException extends DomainException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $promotionCode = null,
    ) {
        parent::__construct($reason);
    }

    public static function invalidCode(string $code): self
    {
        return new self(
            reason: "Promotion code '{$code}' is not valid or does not exist",
            promotionCode: $code,
        );
    }

    public static function expired(string $code): self
    {
        return new self(
            reason: "Promotion '{$code}' has expired",
            promotionCode: $code,
        );
    }

    public static function notYetStarted(string $code): self
    {
        return new self(
            reason: "Promotion '{$code}' has not started yet",
            promotionCode: $code,
        );
    }

    public static function usageLimitReached(string $code): self
    {
        return new self(
            reason: "Promotion '{$code}' usage limit has been reached",
            promotionCode: $code,
        );
    }

    public static function minimumNotMet(string $code, int $minimumCents, int $subtotalCents): self
    {
        $minimum = number_format($minimumCents / 100, 2);
        $subtotal = number_format($subtotalCents / 100, 2);

        return new self(
            reason: "Minimum order amount of \${$minimum} not met. Current subtotal: \${$subtotal}",
            promotionCode: $code,
        );
    }

    public static function noEligibleItems(string $code): self
    {
        return new self(
            reason: "No items in cart are eligible for promotion '{$code}'",
            promotionCode: $code,
        );
    }
}
