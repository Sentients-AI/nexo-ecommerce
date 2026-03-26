<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $activeFilter = $request->query('active');

        $products = Product::query()
            ->with(['stock:id,product_id,quantity_available', 'category:id,name'])
            ->withCount('variants')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($activeFilter !== null, fn ($q) => $q->where('is_active', $activeFilter === '1'))
            ->latest()
            ->paginate(20)
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'slug' => $p->slug,
                'price_cents' => $p->price_cents,
                'sale_price' => $p->sale_price,
                'is_active' => $p->is_active,
                'is_featured' => $p->is_featured,
                'category' => $p->category ? ['name' => $p->category->name] : null,
                'stock' => $p->stock ? ['quantity_available' => $p->stock->quantity_available] : null,
                'variants_count' => $p->variants_count,
                'images' => $p->images,
            ]);

        $totalActive = Product::query()->where('is_active', true)->count();
        $totalInactive = Product::query()->where('is_active', false)->count();

        return Inertia::render('Vendor/Products', [
            'products' => $products,
            'search' => $search,
            'active_filter' => $activeFilter,
            'stats' => [
                'total_active' => $totalActive,
                'total_inactive' => $totalInactive,
            ],
        ]);
    }
}
