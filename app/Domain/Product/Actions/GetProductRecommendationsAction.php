<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class GetProductRecommendationsAction
{
    private const int LIMIT = 8;

    private const int CACHE_TTL_SECONDS = 3600;

    private const int MIN_CO_PURCHASE_RESULTS = 2;

    /**
     * Return products frequently bought together with the given product.
     *
     * Algorithm:
     *  1. Find all order_ids that contain this product.
     *  2. Count how often each other product appears in those same orders.
     *  3. Sort by frequency descending and return up to LIMIT products.
     *  4. Fall back to same-category products when co-purchase data is thin.
     *
     * @return Collection<int, Product>
     */
    public function execute(Product $product): Collection
    {
        $cacheKey = "recommendations:product:{$product->id}:tenant:{$product->tenant_id}";

        /** @var Collection<int, Product> */
        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($product): Collection {
            $recommendations = $this->getCoPurchaseRecommendations($product);

            if ($recommendations->count() < self::MIN_CO_PURCHASE_RESULTS) {
                return $this->getCategoryFallback($product, $recommendations->pluck('id')->all());
            }

            return $recommendations;
        });
    }

    /**
     * @return Collection<int, Product>
     */
    private function getCoPurchaseRecommendations(Product $product): Collection
    {
        // IDs of orders that contain this product
        $orderIds = OrderItem::query()
            ->where('product_id', $product->id)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return new Collection;
        }

        // Count co-purchases: other products that appear in those orders
        $coProductIds = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $product->id)
            ->selectRaw('product_id, COUNT(*) as frequency')
            ->groupBy('product_id')
            ->orderByDesc('frequency')
            ->limit(self::LIMIT)
            ->pluck('product_id');

        if ($coProductIds->isEmpty()) {
            return new Collection;
        }

        // Fetch in frequency order, active products only
        $products = Product::query()
            ->whereIn('id', $coProductIds)
            ->where('is_active', true)
            ->with(['stock'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->get()
            ->sortBy(fn (Product $p) => $coProductIds->search($p->id))
            ->values();

        return $products;
    }

    /**
     * @param  array<int>  $excludeIds
     * @return Collection<int, Product>
     */
    private function getCategoryFallback(Product $product, array $excludeIds): Collection
    {
        $excludeIds[] = $product->id;

        return Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->whereNotIn('id', $excludeIds)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['stock'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->inRandomOrder()
            ->limit(self::LIMIT)
            ->get();
    }
}
