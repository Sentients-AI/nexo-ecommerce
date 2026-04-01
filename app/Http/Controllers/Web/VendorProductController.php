<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\VendorProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function create(): Response
    {
        $categories = Category::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Vendor/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(VendorProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $product = Product::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'price_cents' => (int) round((float) $validated['price_cents'] * 100),
            'sale_price' => isset($validated['sale_price']) ? (int) round((float) $validated['sale_price'] * 100) : null,
            'category_id' => $validated['category_id'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'images' => $validated['images'] ?? null,
        ]);

        $product->stock()->create([
            'quantity_available' => 0,
            'quantity_reserved' => 0,
        ]);

        return redirect('/vendor/products')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): Response
    {
        $categories = Category::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Vendor/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'price' => round((float) $product->price_cents / 100, 2),
                'sale_price' => $product->sale_price ? round((float) $product->sale_price / 100, 2) : null,
                'category_id' => $product->category_id,
                'is_active' => $product->is_active,
                'is_featured' => $product->is_featured,
                'images' => $product->images ?? [],
            ],
            'categories' => $categories,
        ]);
    }

    public function update(VendorProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        $product->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'price_cents' => (int) round((float) $validated['price_cents'] * 100),
            'sale_price' => isset($validated['sale_price']) ? (int) round((float) $validated['sale_price'] * 100) : null,
            'category_id' => $validated['category_id'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'images' => $validated['images'] ?? null,
        ]);

        return redirect('/vendor/products')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect('/vendor/products')->with('success', 'Product deleted successfully.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $query = Product::query()->whereIn('id', $validated['ids']);

        match ($validated['action']) {
            'activate' => $query->update(['is_active' => true]),
            'deactivate' => $query->update(['is_active' => false]),
            'delete' => $query->delete(),
        };

        $count = count($validated['ids']);
        $label = match ($validated['action']) {
            'activate' => 'activated',
            'deactivate' => 'deactivated',
            'delete' => 'deleted',
        };

        return back()->with('success', "{$count} product(s) {$label} successfully.");
    }
}
