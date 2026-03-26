<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class SearchController extends Controller
{
    /**
     * Search products by query with optional filters.
     */
    public function products(Request $request): JsonResponse
    {
        $tenantId = Context::get('tenant_id');
        $perPage = min((int) $request->input('per_page', 15), 100);
        $facets = null;

        if ($request->filled('q')) {
            // Attempt Typesense native faceting via raw() for efficient aggregation
            $rawResult = Product::search($request->q)
                ->options([
                    'facet_by' => 'category_name,price_cents',
                    'max_facet_values' => 20,
                ])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->raw();

            if (is_array($rawResult) && isset($rawResult['facet_counts'])) {
                $facets = $this->parseFacetsFromTypesense($rawResult['facet_counts']);
                $productIds = collect($rawResult['hits'] ?? [])->pluck('document.id');
            } else {
                // Fallback for non-Typesense drivers (e.g. collection in tests)
                $productIds = Product::search($request->q)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->keys();
            }

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

        // Compute facets from DB if not already set from Typesense
        if ($facets === null) {
            $facets = $this->computeDbFacets(clone $query);
        }

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
            'facets' => $facets,
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

    /**
     * Compute facets (categories + price range) using DB aggregation.
     *
     * @return array{categories: array<int, array{slug: string, name: string, count: int}>, price_range: array{min: int, max: int}}
     */
    private function computeDbFacets(Builder $baseQuery): array
    {
        // Use toBase() to strip eager loads — prevents MissingAttributeException when
        // only a subset of columns is selected (e.g. just 'id' for the subquery).
        $baseQueryBuilder = (clone $baseQuery)->toBase();

        $productIds = (clone $baseQueryBuilder)->pluck('id');

        $categoryFacets = Category::query()
            ->select('categories.slug', 'categories.name')
            ->selectRaw('COUNT(products.id) as count')
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->whereIn('products.id', $productIds)
            ->groupBy('categories.id', 'categories.slug', 'categories.name')
            ->get()
            ->map(fn (Category $row): array => [
                'slug' => $row->slug,
                'name' => $row->name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $priceStats = (clone $baseQueryBuilder)
            ->selectRaw('MIN(price_cents) as min_price, MAX(price_cents) as max_price')
            ->first();

        return [
            'categories' => $categoryFacets,
            'price_range' => [
                'min' => (int) ($priceStats?->min_price ?? 0),
                'max' => (int) ($priceStats?->max_price ?? 0),
            ],
        ];
    }

    /**
     * Parse facet data from a Typesense facet_counts response.
     *
     * @param  array<int, array<string, mixed>>  $facetCounts
     * @return array{categories: array<int, array{slug: string, name: string, count: int}>, price_range: array{min: int, max: int}}
     */
    private function parseFacetsFromTypesense(array $facetCounts): array
    {
        $categories = [];
        $priceRange = ['min' => 0, 'max' => 0];

        foreach ($facetCounts as $facet) {
            if ($facet['field_name'] === 'category_name') {
                $categories = collect($facet['counts'] ?? [])
                    ->map(fn (array $entry): array => [
                        'slug' => Str::slug($entry['value']),
                        'name' => $entry['value'],
                        'count' => (int) $entry['count'],
                    ])
                    ->toArray();
            } elseif ($facet['field_name'] === 'price_cents') {
                $stats = $facet['stats'] ?? [];
                $priceRange = [
                    'min' => (int) ($stats['min'] ?? 0),
                    'max' => (int) ($stats['max'] ?? 0),
                ];
            }
        }

        return [
            'categories' => $categories,
            'price_range' => $priceRange,
        ];
    }
}
