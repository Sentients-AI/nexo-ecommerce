<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Specifications;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use App\Shared\Specifications\AbstractSpecification;

/**
 * Checks if a promotion applies to at least one item in the cart.
 *
 * @extends AbstractSpecification<array{promotion: Promotion, cart: Cart}>
 */
final class PromotionAppliesToCart extends AbstractSpecification
{
    public function __construct(
        private readonly Promotion $promotion,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof Cart) {
            $this->setFailureReason('Candidate must be a Cart instance');

            return false;
        }

        // Check minimum order requirement
        $subtotal = (int) $candidate->subtotal;
        if (! $this->promotion->meetsMinimumOrder($subtotal)) {
            $this->setFailureReason(
                "Order subtotal ({$subtotal}) does not meet minimum requirement ({$this->promotion->minimum_order_cents})"
            );

            return false;
        }

        // For 'all' scope, promotion applies to everything
        if ($this->promotion->scope === PromotionScope::All) {
            return true;
        }

        // Check if at least one cart item matches the promotion scope
        $hasEligibleItem = false;
        foreach ($candidate->items as $item) {
            if ($this->promotion->appliesTo($item->product)) {
                $hasEligibleItem = true;
                break;
            }
        }

        if (! $hasEligibleItem) {
            $this->setFailureReason('No items in cart match the promotion scope');

            return false;
        }

        return true;
    }
}
