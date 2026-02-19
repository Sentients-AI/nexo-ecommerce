<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'stock'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($q) => $q->where('slug', $request->category)))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            // Price filters
            ->when($request->filled('min_price'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('price_cents', '>=', (int) $request->min_price)
                    ->orWhere('sale_price', '>=', (int) $request->min_price);
            }))
            ->when($request->filled('max_price'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->whereNull('sale_price')->where('price_cents', '<=', (int) $request->max_price);
                })->orWhere(function ($sub) use ($request) {
                    $sub->whereNotNull('sale_price')->where('sale_price', '<=', (int) $request->max_price);
                });
            }))
            // In stock filter
            ->when($request->boolean('in_stock'), fn ($q) => $q->whereHas('stock', fn ($s) => $s->where('quantity_available', '>', 0)))
            // On sale filter
            ->when($request->boolean('on_sale'), fn ($q) => $q->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price_cents'))
            // Sorting
            ->when($request->filled('sort'), fn ($q) => match ($request->sort) {
                'price_asc' => $q->orderByRaw('COALESCE(sale_price, price_cents) ASC'),
                'price_desc' => $q->orderByRaw('COALESCE(sale_price, price_cents) DESC'),
                'name_asc' => $q->orderBy('name', 'asc'),
                'popular' => $q->orderBy('view_count', 'desc')->orderBy('reviews_avg_rating', 'desc'),
                default => $q->orderBy('created_at', 'desc'),
            }, fn ($q) => $q->orderBy('created_at', 'desc'))
            ->paginate(12)
            ->withQueryString();

        // Load categories for filter sidebar
        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'featured' => $request->boolean('featured'),
                'min_price' => $request->filled('min_price') ? (int) $request->min_price : null,
                'max_price' => $request->filled('max_price') ? (int) $request->max_price : null,
                'in_stock' => $request->boolean('in_stock'),
                'on_sale' => $request->boolean('on_sale'),
            ],
        ]);
    }

    public function wishlist(Request $request): Response
    {
        $ids = array_filter(array_map('intval', explode(',', $request->query('ids', ''))));

        $products = empty($ids) ? collect() : Product::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->with(['category', 'stock'])
            ->get();

        return Inertia::render('Wishlist/Index', [
            'products' => $products,
        ]);
    }

    public function show(string $locale, Product $product): Response
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product->increment('view_count');
        $product->load(['category', 'stock', 'tenant:id,name,slug']);

        $reviewStats = [
            'average_rating' => $product->reviews()->approved()->avg('rating'),
            'review_count' => $product->reviews()->approved()->count(),
        ];

        $relatedProducts = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['stock'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->limit(4)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'reviewStats' => $reviewStats,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
