<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Actions;

use App\Domain\GiftCard\Exceptions\GiftCardException;
use App\Domain\GiftCard\Models\GiftCard;

final readonly class ValidateGiftCardAction
{
    public function execute(string $code): GiftCard
    {
        $giftCard = GiftCard::query()
            ->where('code', mb_strtoupper(mb_trim($code)))
            ->first();

        if ($giftCard === null) {
            throw GiftCardException::invalidCode();
        }

        if (! $giftCard->is_active) {
            throw GiftCardException::inactive();
        }

        if ($giftCard->expires_at !== null && now()->gt($giftCard->expires_at)) {
            throw GiftCardException::expired();
        }

        if ($giftCard->balance_cents <= 0) {
            throw GiftCardException::noBalance();
        }

        return $giftCard;
    }
}
