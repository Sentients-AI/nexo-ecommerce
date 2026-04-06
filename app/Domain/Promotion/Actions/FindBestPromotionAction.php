<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\DTOs\DiscountCalculationResult;
use App\Domain\Promotion\Exceptions\PromotionNotApplicableException;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Specifications\PromotionAppliesToCart;
use App\Domain\Promotion\Specifications\PromotionIsValid;
use App\Domain\Promotion\Specifications\UserCanUsePromotion;
use App\Domain\User\Models\User;
use Carbon\Carbon;

final readonly class FindBestPromotionAction
{
    public function __construct(
        private CalculateDiscountAction $calculateDiscount,
    ) {}

    /**
     * Find the best promotion for a cart.
     *
     * @return array{promotion: Promotion, result: DiscountCalculationResult}|null
     */
    public function execute(Cart $cart, ?User $user, ?string $code = null): ?array
    {
        $cart->loadMissing('items.product');

        // If a code is provided, look up that specific promotion
        if ($code !== null) {
            return $this->findByCode($cart, $user, $code);
        }

        // Auto-apply promotions require an authenticated user for per-user limit checks
        if ($user === null) {
            return null;
        }

        // Otherwise, find the best auto-apply promotion
        return $this->findBestAutoApply($cart, $user);
    }

    /**
     * Find promotion by code and validate it.
     *
     * @return array{promotion: Promotion, result: DiscountCalculationResult}
     *
     * @throws PromotionNotApplicableException
     */
    private function findByCode(Cart $cart, ?User $user, string $code): array
    {
        $promotion = Promotion::query()
            ->where('code', $code)
            ->with(['products', 'categories'])
            ->first();

        if ($promotion === null) {
            throw PromotionNotApplicableException::invalidCode($code);
        }

        // Validate promotion is valid
        $validSpec = new PromotionIsValid();
        if (! $validSpec->isSatisfiedBy($promotion)) {
            throw new PromotionNotApplicableException(
                reason: $validSpec->getFailureReason(),
                promotionCode: $code,
            );
        }

        // Validate per-user limit only for authenticated users (guests cannot be tracked)
        if ($user !== null) {
            $userSpec = new UserCanUsePromotion($promotion);
            if (! $userSpec->isSatisfiedBy($user)) {
                throw new PromotionNotApplicableException(
                    reason: $userSpec->getFailureReason(),
                    promotionCode: $code,
                );
            }
        }

        // Validate promotion applies to cart
        $cartSpec = new PromotionAppliesToCart($promotion);
        if (! $cartSpec->isSatisfiedBy($cart)) {
            throw new PromotionNotApplicableException(
                reason: $cartSpec->getFailureReason(),
                promotionCode: $code,
            );
        }

        $result = $this->calculateDiscount->execute($cart, $promotion);

        if ($result->discountCents === 0) {
            throw PromotionNotApplicableException::noEligibleItems($code);
        }

        return ['promotion' => $promotion, 'result' => $result];
    }

    /**
     * Find the best auto-apply promotion.
     *
     * @return array{promotion: Promotion, result: DiscountCalculationResult}|null
     */
    private function findBestAutoApply(Cart $cart, User $user): ?array
    {
        $now = Carbon::now();

        // Get all active auto-apply promotions
        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where('auto_apply', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->with(['products', 'categories'])
            ->get();

        $bestResult = null;
        $bestPromotion = null;
        $bestDiscount = 0;

        foreach ($promotions as $promotion) {
            // Check user can use this promotion
            $userSpec = new UserCanUsePromotion($promotion);
            if (! $userSpec->isSatisfiedBy($user)) {
                continue;
            }

            // Check promotion applies to cart
            $cartSpec = new PromotionAppliesToCart($promotion);
            if (! $cartSpec->isSatisfiedBy($cart)) {
                continue;
            }

            // Calculate discount
            $result = $this->calculateDiscount->execute($cart, $promotion);

            // Track the best discount
            if ($result->discountCents > $bestDiscount) {
                $bestDiscount = $result->discountCents;
                $bestResult = $result;
                $bestPromotion = $promotion;
            }
        }

        if ($bestPromotion === null || ! $bestResult instanceof DiscountCalculationResult) {
            return null;
        }

        return ['promotion' => $bestPromotion, 'result' => $bestResult];
    }
}
