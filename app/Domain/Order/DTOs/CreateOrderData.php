<?php

declare(strict_types=1);

namespace App\Domain\Order\DTOs;

use App\Shared\DTOs\BaseData;

final class CreateOrderData extends BaseData
{
    /**
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     */
    public function __construct(
        public string|int|null $userId,
        public string $cartId,
        public ?array $shippingAddress = null,
        public ?array $billingAddress = null,
        public ?string $notes = null,
        public string $currency = 'MYR',
        public ?int $promotionId = null,
        public int $discountCents = 0,
        public int $loyaltyDiscountCents = 0,
        public int $giftCardDiscountCents = 0,
        public ?string $giftCardCode = null,
        public ?int $shippingMethodId = null,
        public ?string $guestEmail = null,
        public ?string $guestName = null,
    ) {}

    /**
     * Create from request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            cartId: $data['cart_id'],
            shippingAddress: $data['shipping_address'] ?? null,
            billingAddress: $data['billing_address'] ?? null,
            notes: $data['notes'] ?? null,
            currency: $data['currency'] ?? 'MYR',
            promotionId: $data['promotion_id'] ?? null,
            discountCents: $data['discount_cents'] ?? 0,
        );
    }
}
