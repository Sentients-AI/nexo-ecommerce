<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutController extends Controller
{
    public function summary(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->with('items.product.stock')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index', ['locale' => app()->getLocale()])
                ->with('error', 'Your cart is empty.');
        }

        return Inertia::render('Checkout/Summary', [
            'cart' => [
                'id' => $cart->id,
                'items' => $cart->items->map(fn (CartItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'images' => $item->product->images,
                        'stock' => $item->product->stock ? [
                            'available' => $item->product->stock->quantity_available - $item->product->stock->quantity_reserved,
                        ] : null,
                    ] : null,
                ])->toArray(),
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal,
            ],
            'stripePublicKey' => config('services.stripe.key'),
        ]);
    }

    public function pending(Request $request): Response|RedirectResponse
    {
        $orderId = $request->query('order_id');

        if (! $orderId) {
            return redirect()->route('checkout.summary', ['locale' => app()->getLocale()]);
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->with(['items', 'paymentIntent'])
            ->first();

        if (! $order) {
            return redirect()->route('checkout.summary', ['locale' => app()->getLocale()])
                ->with('error', 'Order not found.');
        }

        // If order is already paid, redirect to result
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled, OrderStatus::Shipped, OrderStatus::Delivered], true)) {
            return redirect()->route('checkout.result', ['locale' => app()->getLocale(), 'order_id' => $order->id]);
        }

        return Inertia::render('Checkout/Pending', [
            'order' => $this->formatOrder($order),
            'clientSecret' => $order->paymentIntent?->client_secret,
            'stripePublicKey' => config('services.stripe.key'),
        ]);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $orderId = $request->query('order_id');

        if (! $orderId) {
            return redirect()->route('orders.index', ['locale' => app()->getLocale()]);
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->with(['items', 'paymentIntent'])
            ->first();

        if (! $order) {
            return redirect()->route('orders.index', ['locale' => app()->getLocale()])
                ->with('error', 'Order not found.');
        }

        return Inertia::render('Checkout/Result', [
            'order' => $this->formatOrder($order),
            'success' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled, OrderStatus::Shipped, OrderStatus::Delivered], true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'subtotal_cents' => $order->subtotal_cents,
            'tax_cents' => $order->tax_cents,
            'shipping_cost_cents' => $order->shipping_cost_cents,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
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
                'client_secret' => $order->paymentIntent->client_secret,
            ] : null,
            'created_at' => $order->created_at->toISOString(),
        ];
    }
}
