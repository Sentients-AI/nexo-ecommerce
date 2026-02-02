<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\DTOs\Request\CheckoutRequest as CheckoutUseCaseRequest;
use App\Application\UseCases\Order\CheckoutUseCase;
use App\Domain\Cart\Exceptions\EmptyCartException;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\ValueObjects\CartId;
use App\Domain\Idempotency\Actions\EnsureIdempotentAction;
use App\Domain\Idempotency\Actions\StoreIdempotencyResultAction;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\ConfirmPaymentIntentAction;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Shared\Enums\ErrorCode;
use App\Domain\User\ValueObjects\UserId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Requests\Api\V1\ConfirmPaymentRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\PaymentIntentResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutUseCase $checkoutUseCase,
        private readonly ConfirmPaymentIntentAction $confirmPaymentIntent,
        private readonly EnsureIdempotentAction $ensureIdempotent,
        private readonly StoreIdempotencyResultAction $storeIdempotency,
    ) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        $user = $request->user();

        if ($idempotencyKey !== null && $user !== null) {
            $payload = $request->validated();
            $cachedResponse = $this->ensureIdempotent->execute(
                $idempotencyKey,
                $user->id,
                'checkout',
                $payload
            );

            if ($cachedResponse !== null) {
                return response()->json($cachedResponse);
            }
        }

        try {
            $cart = Cart::query()->findOrFail($request->validated('cart_id'));

            if ($cart->user_id !== $user?->id) {
                return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this cart.');
            }

            $response = $this->checkoutUseCase->execute(
                new CheckoutUseCaseRequest(
                    userId: UserId::fromInt($user->id),
                    cartId: CartId::fromInt($cart->id),
                    currency: $request->validated('currency'),
                    idempotencyKey: $idempotencyKey,
                )
            );

            $order = Order::query()->with('items')->find($response->orderId->toInt());
            $paymentIntent = PaymentIntent::query()->find($response->paymentIntentId?->toInt());

            $responseData = [
                'order' => new OrderResource($order),
                'payment_intent' => new PaymentIntentResource($paymentIntent),
            ];

            if ($idempotencyKey !== null && $user !== null) {
                $this->storeIdempotency->execute(
                    $idempotencyKey,
                    $user->id,
                    'checkout',
                    $request->validated(),
                    200,
                    $responseData
                );
            }

            return response()->json($responseData);

        } catch (EmptyCartException) {
            return $this->errorResponse(ErrorCode::CartEmpty, 'Cannot checkout with an empty cart.');
        } catch (InsufficientStockException $e) {
            return $this->errorResponse(
                ErrorCode::InsufficientStock,
                "Insufficient stock for product {$e->productId}. Requested: {$e->requested}, Available: {$e->available}"
            );
        } catch (DomainException $e) {
            // Cart empty from specification
            if (str_contains($e->getMessage(), 'Cart is empty')) {
                return $this->errorResponse(ErrorCode::CartEmpty, 'Cannot checkout with an empty cart.');
            }

            return $this->errorResponse(ErrorCode::ValidationFailed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred during checkout.');
        }
    }

    public function confirmPayment(ConfirmPaymentRequest $request): JsonResponse
    {
        try {
            $paymentIntent = PaymentIntent::query()
                ->with('order')
                ->findOrFail($request->validated('payment_intent_id'));

            $user = $request->user();
            if ($paymentIntent->order?->user_id !== $user?->id) {
                return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this payment.');
            }

            $confirmedIntent = $this->confirmPaymentIntent->execute($paymentIntent);

            return response()->json([
                'payment_intent' => new PaymentIntentResource($confirmedIntent),
                'order' => new OrderResource($confirmedIntent->order->fresh('items')),
            ]);

        } catch (DomainException $e) {
            return $this->errorResponse(ErrorCode::PaymentFailed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::PaymentGatewayError, 'Payment confirmation failed.');
        }
    }

    private function errorResponse(ErrorCode $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'retryable' => $code->isRetryable(),
            ],
        ], $code->httpStatus());
    }
}
