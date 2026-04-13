<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Actions;

use App\Domain\GiftCard\Models\GiftCard;
use App\Domain\GiftCard\Models\GiftCardRedemption;
use App\Domain\Order\Models\Order;

final readonly class RedeemGiftCardAction
{
    /**
     * Deduct balance and record redemption.
     * Must be called inside an existing DB transaction with lockForUpdate on the gift card.
     */
    public function execute(GiftCard $giftCard, Order $order, int $amountCents): GiftCardRedemption
    {
        $giftCard->deductBalance($amountCents);

        return GiftCardRedemption::query()->create([
            'gift_card_id' => $giftCard->id,
            'order_id' => $order->id,
            'amount_cents' => $amountCents,
        ]);
    }
}
