<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

final class SearchController extends Controller
{
    /**
     * Search products by query with optional filters.
     */
    public function products(Request $request): JsonResponse
    {
        $tenantId = Context::get('tenant_id');
        $perPage = min((int) $request->input('per_page', 15), 100);

        if ($request->filled('q')) {
            $productIds = Product::search($request->q)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->keys();

            $query = Product::query()
                ->whereKey($productIds)
                ->where('is_active', true)
                ->with(['category']);
        } else {
            $query = Product::query()
                ->where('is_active', true)
                ->with(['category']);
        }

        $query
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($q) => $q->where('slug', $request->category)))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price_cents', '>=', (int) $request->min_price))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price_cents', '<=', (int) $request->max_price));

        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price_cents' => $product->price_cents,
                'sale_price' => $product->sale_price,
                'currency' => $product->currency,
                'is_featured' => $product->is_featured,
                'category_name' => $product->category?->name,
                'image' => is_array($product->images) ? ($product->images[0] ?? null) : null,
            ]),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Search categories by query.
     */
    public function categories(Request $request): JsonResponse
    {
        $tenantId = Context::get('tenant_id');
        $perPage = min((int) $request->input('per_page', 15), 100);

        if ($request->filled('q')) {
            $categoryIds = Category::search($request->q)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->keys();

            $query = Category::query()
                ->whereKey($categoryIds)
                ->where('is_active', true);
        } else {
            $query = Category::query()->where('is_active', true);
        }

        $categories = $query->paginate($perPage);

        return response()->json([
            'data' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ]),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Search orders for the authenticated user.
     */
    public function orders(Request $request): JsonResponse
    {
        $tenantId = Context::get('tenant_id');
        $userId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 15), 100);

        if ($request->filled('q')) {
            $orderIds = Order::search($request->q)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->keys();

            $query = Order::query()
                ->whereKey($orderIds)
                ->where('user_id', $userId);
        } else {
            $query = Order::query()->where('user_id', $userId);
        }

        $query->when(
            $request->filled('status'),
            fn ($q) => $q->where('status', OrderStatus::from($request->status))
        );

        $orders = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
            ]),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
