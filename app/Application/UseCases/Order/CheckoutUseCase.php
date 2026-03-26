<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Application\DTOs\Request\CheckoutRequest;
use App\Application\DTOs\Response\CheckoutResponse;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Specifications\CartHasItems;
use App\Domain\Cart\Specifications\CartIsNotCompleted;
use App\Domain\Loyalty\Actions\RedeemPointsAction;
use App\Domain\Loyalty\DTOs\RedeemPointsData;
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

            // Get user for promotion validation
            $user = User::query()->findOrFail($request->userId->toInt());

            // Find and calculate promotion discount
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

            // Validate and calculate loyalty discount if redeem_points requested
            $loyaltyDiscountCents = 0;
            if ($request->redeemPoints !== null && $request->redeemPoints > 0) {
                $loyaltyAccount = LoyaltyAccount::query()
                    ->where('user_id', $request->userId->toInt())
                    ->first();

                if ($loyaltyAccount === null || ! $loyaltyAccount->canRedeem($request->redeemPoints)) {
                    throw new \App\Domain\Loyalty\Exceptions\InsufficientPointsException(
                        $loyaltyAccount?->points_balance ?? 0,
                        $request->redeemPoints
                    );
                }

                $loyaltyDiscountCents = $request->redeemPoints * (int) config('loyalty.points_value_cents');
            }

            // Create order through domain action
            $order = $this->createOrderFromCart->execute(
                new CreateOrderData(
                    userId: $request->userId->toInt(),
                    cartId: (string) $request->cartId->toInt(),
                    currency: $request->currency,
                    promotionId: $promotionId,
                    discountCents: $discountCents,
                    loyaltyDiscountCents: $loyaltyDiscountCents,
                )
            );

            // Record promotion usage if a promotion was applied
            if ($promotion instanceof Promotion && $discountCents > 0) {
                $this->recordPromotionUsage->execute(
                    $promotion,
                    $user,
                    $order,
                    $discountCents
                );
            }

            // Redeem loyalty points after order creation (inside same transaction for atomicity)
            if ($request->redeemPoints !== null && $request->redeemPoints > 0) {
                $this->redeemPoints->execute(new RedeemPointsData(
                    userId: $request->userId->toInt(),
                    points: $request->redeemPoints,
                    description: "Redeemed {$request->redeemPoints} points for order {$order->order_number}",
                    referenceType: 'orders',
                    referenceId: $order->id,
                ));
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
