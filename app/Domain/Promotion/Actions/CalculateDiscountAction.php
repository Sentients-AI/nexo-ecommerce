<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\DTOs\DiscountCalculationResult;
use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Specifications\PromotionIsValid;

final readonly class CalculateDiscountAction
{
    public function execute(Cart $cart, Promotion $promotion): DiscountCalculationResult
    {
        // Validate promotion is still valid
        $validSpec = new PromotionIsValid();
        if (! $validSpec->isSatisfiedBy($promotion)) {
            return DiscountCalculationResult::zero();
        }

        // Load products and categories for scope checking
        $promotion->loadMissing(['products', 'categories']);
        $cart->loadMissing('items.product');

        // Filter cart items by promotion scope and calculate eligible subtotal
        $eligibleItemIds = [];
        $eligibleSubtotalCents = 0;

        foreach ($cart->items as $item) {
            $isEligible = match ($promotion->scope) {
                PromotionScope::All => true,
                PromotionScope::Product => $promotion->products->contains('id', $item->product_id),
                PromotionScope::Category => $item->product->category_id !== null
                    && $promotion->categories->contains('id', $item->product->category_id),
            };

            if ($isEligible) {
                $eligibleItemIds[] = $item->id;
                $eligibleSubtotalCents += $item->price_cents_snapshot * $item->quantity;
            }
        }

        // No eligible items
        if ($eligibleSubtotalCents === 0) {
            return DiscountCalculationResult::zero();
        }

        // Check minimum order requirement against cart subtotal
        $cartSubtotal = (int) $cart->subtotal;
        if (! $promotion->meetsMinimumOrder($cartSubtotal)) {
            return DiscountCalculationResult::zero();
        }

        // Calculate discount based on type
        $discountCents = match ($promotion->discount_type) {
            DiscountType::Bogo => $promotion->calculateBogoDiscount(
                $this->expandItemsToUnitPrices($cart, $eligibleItemIds),
            ),
            DiscountType::Tiered => $promotion->calculateTieredDiscount($eligibleSubtotalCents),
            default => $promotion->calculateDiscount($eligibleSubtotalCents),
        };

        return new DiscountCalculationResult(
            discountCents: $discountCents,
            eligibleSubtotalCents: $eligibleSubtotalCents,
            promotionId: $promotion->id,
            eligibleItemIds: $eligibleItemIds,
        );
    }

    /**
     * Expand eligible cart items into a flat array of unit prices (one entry per quantity unit).
     *
     * @param  array<int>  $eligibleItemIds
     * @return array<int>
     */
    private function expandItemsToUnitPrices(Cart $cart, array $eligibleItemIds): array
    {
        $prices = [];

        foreach ($cart->items as $item) {
            if (! in_array($item->id, $eligibleItemIds, true)) {
                continue;
            }

            for ($i = 0; $i < $item->quantity; $i++) {
                $prices[] = $item->price_cents_snapshot;
            }
        }

        return $prices;
    }
}
