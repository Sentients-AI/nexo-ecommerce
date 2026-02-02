<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('Orders/Index', [
            'orders' => [
                'data' => $orders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'total_cents' => $order->total_cents,
                    'currency' => $order->currency,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at->toISOString(),
                ]),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
                'links' => [
                    'prev' => $orders->previousPageUrl(),
                    'next' => $orders->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load(['items', 'paymentIntent', 'refunds']);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_cents,
                'shipping_cost_cents' => $order->shipping_cost_cents,
                'total_cents' => $order->total_cents,
                'refunded_amount_cents' => $order->refunded_amount_cents ?? 0,
                'currency' => $order->currency,
                'is_refundable' => $order->isRefundable(),
                'remaining_refundable_amount' => $order->getRemainingRefundableAmount(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'total_cents' => $item->total_cents,
                ])->toArray(),
                'payment_intent' => $order->paymentIntent ? [
                    'id' => $order->paymentIntent->id,
                    'status' => $order->paymentIntent->status->value ?? $order->paymentIntent->status,
                    'amount_cents' => $order->paymentIntent->amount_cents,
                ] : null,
                'refunds' => $order->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount_cents' => $refund->amount_cents,
                    'status' => $refund->status->value,
                    'reason' => $refund->reason,
                    'created_at' => $refund->created_at->toISOString(),
                ])->toArray(),
                'created_at' => $order->created_at->toISOString(),
                'updated_at' => $order->updated_at->toISOString(),
            ],
        ]);
    }
}
