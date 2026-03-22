<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CartController extends Controller
{
    public function index(Request $request): Response
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load('items.product.stock');

        return Inertia::render('Cart/Index', [
            'cart' => $this->formatCart($cart),
        ]);
    }

    private function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        if ($user) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id, 'completed_at' => null],
                ['session_id' => $sessionId]
            );
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'completed_at' => null],
            ['user_id' => null]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCart(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'items' => $cart->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'sku' => $item->product->sku,
                    'images' => $item->product->images,
                    'price_cents' => $item->product->price_cents,
                    'sale_price' => $item->product->sale_price,
                    'stock' => $item->product->stock ? [
                        'quantity' => $item->product->stock->quantity_available,
                        'available' => $item->product->stock->quantity_available - $item->product->stock->quantity_reserved,
                    ] : null,
                ] : null,
            ])->toArray(),
            'total_items' => $cart->total_items,
            'subtotal' => $cart->subtotal,
        ];
    }
}
