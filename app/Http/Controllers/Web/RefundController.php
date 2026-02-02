<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Models\Refund;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RefundController extends Controller
{
    public function create(Request $request, Order $order): Response|RedirectResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $order->isRefundable()) {
            return redirect()->route('orders.show', $order)
                ->with('error', 'This order cannot be refunded.');
        }

        $order->load('items');

        return Inertia::render('Refunds/Request', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total_cents' => $order->total_cents,
                'refunded_amount_cents' => $order->refunded_amount_cents ?? 0,
                'remaining_refundable_amount' => $order->getRemainingRefundableAmount(),
                'currency' => $order->currency,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'total_cents' => $item->total_cents,
                ])->toArray(),
                'created_at' => $order->created_at->toISOString(),
            ],
        ]);
    }

    public function show(Request $request, Refund $refund): Response|RedirectResponse
    {
        $refund->load('order');

        if ($refund->order->user_id !== $request->user()->id) {
            abort(403);
        }

        return Inertia::render('Refunds/Status', [
            'refund' => [
                'id' => $refund->id,
                'order_id' => $refund->order_id,
                'amount_cents' => $refund->amount_cents,
                'currency' => $refund->currency,
                'status' => $refund->status->value,
                'reason' => $refund->reason,
                'created_at' => $refund->created_at->toISOString(),
                'approved_at' => $refund->approved_at?->toISOString(),
            ],
            'order' => [
                'id' => $refund->order->id,
                'order_number' => $refund->order->order_number,
                'status' => $refund->order->status->value,
            ],
        ]);
    }
}
