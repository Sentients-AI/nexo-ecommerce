<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load('items.product');

        return response()->json([
            'cart' => $this->formatCart($cart),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $product = Product::query()
            ->where('id', $validated['product_id'])
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return $this->errorResponse(ErrorCode::ProductNotFound);
        }

        $cart = $this->getOrCreateCart($request);

        if ($cart->isCompleted()) {
            return $this->errorResponse(ErrorCode::CartAlreadyCompleted);
        }

        // Check if item already exists in cart
        $existingItem = $cart->items()->where('product_id', $product->id)->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $validated['quantity'],
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'price_cents_snapshot' => (int) ($product->sale_price ?? $product->price_cents),
                'tax_cents_snapshot' => 0,
            ]);
        }

        $cart->load('items.product');

        return response()->json([
            'cart' => $this->formatCart($cart),
        ]);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $cart = $this->getOrCreateCart($request);

        if ($cart->isCompleted()) {
            return $this->errorResponse(ErrorCode::CartAlreadyCompleted);
        }

        $item = $cart->items()->where('id', $itemId)->first();

        if (! $item) {
            return $this->errorResponse(ErrorCode::CartNotFound, 'Cart item not found');
        }

        if ($validated['quantity'] === 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $validated['quantity']]);
        }

        $cart->load('items.product');

        return response()->json([
            'cart' => $this->formatCart($cart),
        ]);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        if ($cart->isCompleted()) {
            return $this->errorResponse(ErrorCode::CartAlreadyCompleted);
        }

        $item = $cart->items()->where('id', $itemId)->first();

        if (! $item) {
            return $this->errorResponse(ErrorCode::CartNotFound, 'Cart item not found');
        }

        $item->delete();
        $cart->load('items.product');

        return response()->json([
            'cart' => $this->formatCart($cart),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        if ($cart->isCompleted()) {
            return $this->errorResponse(ErrorCode::CartAlreadyCompleted);
        }

        $cart->items()->delete();
        $cart->load('items.product');

        return response()->json([
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
                ] : null,
            ])->toArray(),
            'total_items' => $cart->total_items,
            'subtotal' => $cart->subtotal,
        ];
    }

    private function errorResponse(ErrorCode $code, ?string $message = null): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message ?? $code->name,
                'retryable' => $code->isRetryable(),
            ],
        ], $code->httpStatus());
    }
}
