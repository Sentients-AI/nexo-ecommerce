<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Models\Order;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'paymentIntent'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()
            ->with(['items', 'paymentIntent', 'refunds'])
            ->find($orderId);

        if ($order === null) {
            return $this->errorResponse(ErrorCode::OrderNotFound, 'Order not found.');
        }

        if ($order->user_id !== $request->user()->id) {
            return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this order.');
        }

        return response()->json([
            'order' => new OrderResource($order),
        ]);
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
