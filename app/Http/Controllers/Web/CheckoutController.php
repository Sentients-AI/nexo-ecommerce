<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\User\Models\Address;
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

        $cartQuery = Cart::query()->whereNull('completed_at')->with('items.product.stock');

        $cart = $user
            ? $cartQuery->where('user_id', $user->id)->first()
            : $cartQuery->where('session_id', $request->session()->getId())->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index', ['locale' => app()->getLocale()])
                ->with('error', 'Your cart is empty.');
        }

        $savedAddresses = $user
            ? $user->addresses()->orderByDesc('is_default')->orderByDesc('created_at')->get()
            : collect();

        $defaultAddress = $savedAddresses->firstWhere('is_default', true);

        $shippingMethods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('rate_cents')
            ->get()
            ->map(fn (ShippingMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'type' => $m->type->value,
                'rate_cents' => $m->rate_cents,
                'cost_cents' => $m->calculateCost((int) $cart->subtotal),
                'estimated_delivery' => $m->estimatedDeliveryLabel(),
            ])->toArray();

        return Inertia::render('Checkout/Summary', [
            'isAuthenticated' => $user !== null,
            'savedAddresses' => $savedAddresses->map(fn (Address $a): array => $this->formatAddress($a))->toArray(),
            'defaultAddress' => $defaultAddress ? $this->formatAddress($defaultAddress) : null,
            'shippingMethods' => $shippingMethods,
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

        $order = $this->findOrderForRequest($request, (int) $orderId);

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
            $user = $request->user();

            return $user
                ? redirect()->route('orders.index', ['locale' => app()->getLocale()])
                : redirect()->route('home', ['locale' => app()->getLocale()]);
        }

        $order = $this->findOrderForRequest($request, (int) $orderId);

        if (! $order) {
            return redirect()->route('home', ['locale' => app()->getLocale()])
                ->with('error', 'Order not found.');
        }

        return Inertia::render('Checkout/Result', [
            'order' => $this->formatOrder($order),
            'success' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled, OrderStatus::Shipped, OrderStatus::Delivered], true),
            'isGuest' => $order->guest_email !== null,
            'guestEmail' => $order->guest_email,
        ]);
    }

    /**
     * Find an order for the current request, supporting both authenticated users and guests.
     */
    private function findOrderForRequest(Request $request, int $orderId): ?Order
    {
        $user = $request->user();

        $query = Order::query()->where('id', $orderId)->with(['items', 'paymentIntent']);

        if ($user) {
            return $query->where('user_id', $user->id)->first();
        }

        // Guest: match via guest_token stored in session after checkout
        $guestToken = $request->session()->get("guest_order_token_{$orderId}");

        if (! $guestToken) {
            return null;
        }

        return $query->where('guest_token', $guestToken)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'name' => $address->name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'is_default' => $address->is_default,
        ];
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
                'product_name' => $item->product?->name ?? 'Unknown',
                'product_sku' => $item->product?->sku ?? '',
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->price_cents_snapshot,
                'total_cents' => $item->price_cents_snapshot * $item->quantity,
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
