<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Context;
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

    public function invoice(Request $request): HttpResponse
    {
        $orderId = (int) $request->route('orderId');

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->with(['user', 'items.product'])
            ->firstOrFail();

        $tenant = Context::get('tenant');
        $currency = $order->currency ?? 'MYR';

        $formatPrice = function (int|float $cents) use ($currency): string {
            return number_format($cents / 100, 2).' '.$currency;
        };

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'tenant' => $tenant,
            'shippingAddress' => $order->shipping_address,
            'formatPrice' => $formatPrice,
        ]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    public function show(Request $request): Response
    {
        $orderId = (int) $request->route('orderId');

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $order->load(['items.product', 'paymentIntent', 'refunds']);

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
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_sku' => $item->product?->sku ?? '',
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->price_cents_snapshot,
                    'total_cents' => $item->price_cents_snapshot * $item->quantity,
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
                'carrier' => $order->carrier,
                'tracking_number' => $order->tracking_number,
                'shipped_at' => $order->shipped_at?->toISOString(),
                'estimated_delivery_at' => $order->estimated_delivery_at?->toDateString(),
                'created_at' => $order->created_at->toISOString(),
                'updated_at' => $order->updated_at->toISOString(),
            ],
        ]);
    }
}
