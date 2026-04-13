<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Application\DTOs\Request\CheckoutRequest;
use App\Application\DTOs\Response\CheckoutResponse;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Specifications\CartHasItems;
use App\Domain\Cart\Specifications\CartIsNotCompleted;
use App\Domain\GiftCard\Actions\RedeemGiftCardAction;
use App\Domain\GiftCard\Actions\ValidateGiftCardAction;
use App\Domain\GiftCard\Models\GiftCard;
use App\Domain\Loyalty\Actions\RedeemPointsAction;
use App\Domain\Loyalty\DTOs\RedeemPointsData;
use App\Domain\Loyalty\Exceptions\InsufficientPointsException;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Order\Actions\CreateOrderFromCart;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Payment\Actions\CreatePaymentIntentAction;
use App\Domain\Payment\DTOs\CreatePaymentIntentDTO;
use App\Domain\Promotion\Actions\FindBestPromotionAction;
use App\Domain\Promotion\Actions\RecordPromotionUsageAction;
use App\Domain\Promotion\DTOs\DiscountCalculationResult;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\User\Models\User;
use App\Domain\User\ValueObjects\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CheckoutUseCase
{
    public function __construct(
        private CreateOrderFromCart $createOrderFromCart,
        private CreatePaymentIntentAction $createPaymentIntent,
        private FindBestPromotionAction $findBestPromotion,
        private RecordPromotionUsageAction $recordPromotionUsage,
        private RedeemPointsAction $redeemPoints,
        private ValidateGiftCardAction $validateGiftCard,
        private RedeemGiftCardAction $redeemGiftCard,
    ) {}

    public function execute(CheckoutRequest $request): CheckoutResponse
    {
        return DB::transaction(function () use ($request): CheckoutResponse {
            // Load cart with items
            $cart = Cart::query()
                ->with('items.product')
                ->findOrFail($request->cartId->toInt());

            // Validate using composed specifications
            $cartSpec = (new CartIsNotCompleted())->and(new CartHasItems());
            $cartSpec->assertSatisfiedBy($cart);

            // Load user if authenticated (null for guest checkout)
            $user = $request->userId instanceof UserId
                ? User::query()->findOrFail($request->userId->toInt())
                : null;

            // Find and calculate promotion discount (guests can use code-based promotions)
            $promotionId = null;
            $discountCents = 0;
            $promotion = null;

            $promotionResult = $this->findBestPromotion->execute(
                $cart,
                $user,
                $request->promotionCode
            );

            if ($promotionResult !== null) {
                $promotion = $promotionResult['promotion'];
                /** @var DiscountCalculationResult $discountResult */
                $discountResult = $promotionResult['result'];
                $promotionId = $promotion->id;
                $discountCents = $discountResult->discountCents;
            }

            // Loyalty discount only for authenticated users
            $loyaltyDiscountCents = 0;
            if ($user !== null && $request->redeemPoints !== null && $request->redeemPoints > 0) {
                $loyaltyAccount = LoyaltyAccount::query()
                    ->where('user_id', $request->userId->toInt())
                    ->first();

                if ($loyaltyAccount === null || ! $loyaltyAccount->canRedeem($request->redeemPoints)) {
                    throw new InsufficientPointsException(
                        $loyaltyAccount?->points_balance ?? 0,
                        $request->redeemPoints
                    );
                }

                $loyaltyDiscountCents = $request->redeemPoints * (int) config('loyalty.points_value_cents');
            }

            // Gift card discount (works for guests too)
            $giftCard = null;
            $giftCardDiscountCents = 0;
            if ($request->giftCardCode !== null && $request->giftCardCode !== '') {
                $giftCard = $this->validateGiftCard->execute($request->giftCardCode);
                $cartSubtotal = $cart->subtotal - $discountCents - $loyaltyDiscountCents;
                $giftCardDiscountCents = min($giftCard->balance_cents, max(0, (int) $cartSubtotal));
            }

            // Create order through domain action
            $order = $this->createOrderFromCart->execute(
                new CreateOrderData(
                    userId: $request->userId?->toInt(),
                    cartId: (string) $request->cartId->toInt(),
                    currency: $request->currency,
                    promotionId: $promotionId,
                    discountCents: $discountCents,
                    loyaltyDiscountCents: $loyaltyDiscountCents,
                    giftCardDiscountCents: $giftCardDiscountCents,
                    shippingMethodId: $request->shippingMethodId,
                    guestEmail: $request->guestEmail,
                    guestName: $request->guestName,
                )
            );

            // Record promotion usage if a promotion was applied
            if ($promotion instanceof Promotion && $discountCents > 0 && $user !== null) {
                $this->recordPromotionUsage->execute(
                    $promotion,
                    $user,
                    $order,
                    $discountCents
                );
            }

            // Redeem loyalty points after order creation (inside same transaction for atomicity)
            if ($user !== null && $request->redeemPoints !== null && $request->redeemPoints > 0) {
                $this->redeemPoints->execute(new RedeemPointsData(
                    userId: $request->userId->toInt(),
                    points: $request->redeemPoints,
                    description: "Redeemed {$request->redeemPoints} points for order {$order->order_number}",
                    referenceType: 'orders',
                    referenceId: $order->id,
                ));
            }

            // Redeem gift card after order creation (inside same transaction)
            if ($giftCard instanceof GiftCard && $giftCardDiscountCents > 0) {
                $lockedCard = GiftCard::query()->lockForUpdate()->findOrFail($giftCard->id);
                $this->redeemGiftCard->execute($lockedCard, $order, $giftCardDiscountCents);
            }

            // Create payment intent
            $idempotencyKey = $request->idempotencyKey ?? Str::uuid()->toString();
            $paymentIntent = $this->createPaymentIntent->execute(
                new CreatePaymentIntentDTO(
                    orderId: $order->id,
                    amount: $order->total_cents,
                    currency: $order->currency,
                    idempotencyKey: $idempotencyKey,
                )
            );

            return CheckoutResponse::fromOrder($order, $paymentIntent);
        });
    }
}
