<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

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
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($q) => $q->where('slug', $request->category)))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'featured' => $request->boolean('featured'),
            ],
        ]);
    }

    public function show(Product $product): Response
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product->load(['category', 'stock']);

        $relatedProducts = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['stock'])
            ->limit(4)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
