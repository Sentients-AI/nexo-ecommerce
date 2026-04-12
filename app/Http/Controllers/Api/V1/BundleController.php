<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Bundle\Models\BundleItem;
use App\Domain\Cart\Models\Cart;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BundleController extends Controller
{
    /**
     * List all active bundles.
     */
    public function index(): JsonResponse
    {
        $bundles = Bundle::query()
            ->where('is_active', true)
            ->with('items.product', 'items.variant')
            ->latest()
            ->get();

        return response()->json([
            'bundles' => $bundles->map(fn (Bundle $bundle) => $this->formatBundle($bundle)),
        ]);
    }

    /**
     * Show a single bundle by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $bundle = Bundle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('items.product.stock', 'items.variant')
            ->first();

        if (! $bundle) {
            return response()->json(['error' => ['code' => ErrorCode::ProductNotFound->value, 'message' => 'Bundle not found']], 404);
        }

        return response()->json([
            'bundle' => $this->formatBundle($bundle, detailed: true),
        ]);
    }

    /**
     * Add a bundle to the cart as a single line item.
     */
    public function addToCart(Request $request, string $slug): JsonResponse
    {
        $bundle = Bundle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('items.product.stock', 'items.variant')
            ->first();

        if (! $bundle) {
            return response()->json(['error' => ['code' => ErrorCode::ProductNotFound->value, 'message' => 'Bundle not found']], 404);
        }

        // Validate all constituent products are in stock
        foreach ($bundle->items as $item) {
            $stock = $item->variant?->stock ?? $item->product->stock;
            $needed = $item->quantity;

            if (! $stock || $stock->quantity_available < $needed) {
                return response()->json([
                    'error' => [
                        'code' => ErrorCode::InsufficientStock->value,
                        'message' => "'{$item->product->name}' is out of stock.",
                    ],
                ], 422);
            }
        }

        $cart = $this->getOrCreateCart($request);

        if ($cart->isCompleted()) {
            return response()->json(['error' => ['code' => ErrorCode::CartAlreadyCompleted->value, 'message' => 'Cart already completed']], 422);
        }

        // Each bundle is one line item; prevent duplicates
        $existingItem = $cart->items()->where('bundle_id', $bundle->id)->first();

        if ($existingItem) {
            $existingItem->increment('quantity');
        } else {
            $cart->items()->create([
                'bundle_id' => $bundle->id,
                'product_id' => null,
                'variant_id' => null,
                'quantity' => 1,
                'price_cents_snapshot' => $bundle->price_cents,
                'tax_cents_snapshot' => 0,
            ]);
        }

        $cart->load('items.product', 'items.variant.attributeValues.attributeType', 'items.bundle.items.product');

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
    private function formatBundle(Bundle $bundle, bool $detailed = false): array
    {
        $data = [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'slug' => $bundle->slug,
            'description' => $bundle->description,
            'price_cents' => $bundle->price_cents,
            'compare_at_price_cents' => $bundle->compare_at_price_cents,
            'savings_percent' => $bundle->savings_percent,
            'images' => $bundle->images ?? [],
            'items' => $bundle->items->map(fn (BundleItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'images' => $item->product->images,
                    'price_cents' => $item->product->price_cents,
                ] : null,
                'variant' => $item->variant ? [
                    'id' => $item->variant->id,
                    'sku' => $item->variant->sku,
                ] : null,
            ])->toArray(),
        ];

        if ($detailed) {
            $data['in_stock'] = $bundle->items->every(function (BundleItem $item): bool {
                $stock = $item->variant?->stock ?? $item->product?->stock;

                return $stock && $stock->quantity_available >= $item->quantity;
            });
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCart(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'items' => $cart->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'bundle_id' => $item->bundle_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'images' => $item->product->images,
                    'price_cents' => $item->product->price_cents,
                ] : null,
                'bundle' => $item->bundle ? [
                    'id' => $item->bundle->id,
                    'name' => $item->bundle->name,
                    'slug' => $item->bundle->slug,
                    'images' => $item->bundle->images,
                    'price_cents' => $item->bundle->price_cents,
                    'items' => $item->bundle->items->map(fn ($bi) => [
                        'product' => ['name' => $bi->product->name],
                        'quantity' => $bi->quantity,
                    ])->toArray(),
                ] : null,
                'variant' => null,
            ])->toArray(),
            'total_items' => $cart->total_items,
            'subtotal' => $cart->subtotal,
        ];
    }
}
