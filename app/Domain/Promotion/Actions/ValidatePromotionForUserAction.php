<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\Exceptions\PromotionNotApplicableException;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Specifications\PromotionAppliesToCart;
use App\Domain\Promotion\Specifications\PromotionIsValid;
use App\Domain\Promotion\Specifications\UserCanUsePromotion;
use App\Domain\User\Models\User;

final readonly class ValidatePromotionForUserAction
{
    /**
     * Validate that a promotion can be used by a user for a specific cart.
     *
     * @throws PromotionNotApplicableException
     */
    public function execute(Promotion $promotion, User $user, Cart $cart): void
    {
        $code = $promotion->code ?? $promotion->name;

        // Validate promotion is currently valid
        $validSpec = new PromotionIsValid();
        if (! $validSpec->isSatisfiedBy($promotion)) {
            throw new PromotionNotApplicableException(
                reason: $validSpec->getFailureReason(),
                promotionCode: $code,
            );
        }

        // Validate user hasn't exceeded per-user limit
        $userSpec = new UserCanUsePromotion($promotion);
        if (! $userSpec->isSatisfiedBy($user)) {
            throw new PromotionNotApplicableException(
                reason: $userSpec->getFailureReason(),
                promotionCode: $code,
            );
        }

        // Validate promotion applies to cart
        $cartSpec = new PromotionAppliesToCart($promotion);
        if (! $cartSpec->isSatisfiedBy($cart)) {
            throw new PromotionNotApplicableException(
                reason: $cartSpec->getFailureReason(),
                promotionCode: $code,
            );
        }
    }
}
