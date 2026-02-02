<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Actions\RequestRefundAction;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateRefundRequest;
use App\Http\Resources\Api\V1\RefundResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

final class RefundController extends Controller
{
    public function __construct(
        private readonly RequestRefundAction $requestRefund,
    ) {}

    public function store(CreateRefundRequest $request, int $orderId): JsonResponse
    {
        $order = Order::query()
            ->with('paymentIntent')
            ->find($orderId);

        if ($order === null) {
            return $this->errorResponse(ErrorCode::OrderNotFound, 'Order not found.');
        }

        if ($order->user_id !== $request->user()->id) {
            return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this order.');
        }

        try {
            $refund = $this->requestRefund->execute(
                $order,
                (int) $request->validated('amount_cents'),
                $request->validated('reason')
            );

            return response()->json([
                'refund' => new RefundResource($refund),
            ], 201);

        } catch (DomainException $e) {
            // Check "exceeds" first because the message contains "refundable" too
            if (str_contains($e->getMessage(), 'exceeds')) {
                return $this->errorResponse(ErrorCode::RefundAmountExceedsLimit, $e->getMessage());
            }
            if (str_contains($e->getMessage(), 'refundable')) {
                return $this->errorResponse(ErrorCode::OrderNotRefundable, $e->getMessage());
            }

            return $this->errorResponse(ErrorCode::ValidationFailed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred while requesting refund.');
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
