<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Bundle\Models\BundleItem;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class VendorBundleController extends Controller
{
    public function index(): Response
    {
        $bundles = Bundle::query()
            ->withCount('items')
            ->latest()
            ->get()
            ->map(fn (Bundle $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'price_cents' => $b->price_cents,
                'compare_at_price_cents' => $b->compare_at_price_cents,
                'savings_percent' => $b->savings_percent,
                'is_active' => $b->is_active,
                'items_count' => $b->items_count,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Vendor/Bundles', [
            'bundles' => $bundles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vendor/Bundles/Create', [
            'products' => Product::query()
                ->where('is_active', true)
                ->select('id', 'name', 'sku', 'price_cents', 'images')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:1'],
            'compare_at_price_cents' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bundle = Bundle::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(4),
            'description' => $validated['description'] ?? null,
            'price_cents' => $validated['price_cents'],
            'compare_at_price_cents' => $validated['compare_at_price_cents'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($validated['items'] as $item) {
            $bundle->items()->create([
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('vendor.bundles.index')->with('success', "Bundle \"{$bundle->name}\" created.");
    }

    public function edit(Bundle $bundle): Response
    {
        $bundle->load('items.product');

        return Inertia::render('Vendor/Bundles/Edit', [
            'bundle' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'price_cents' => $bundle->price_cents,
                'compare_at_price_cents' => $bundle->compare_at_price_cents,
                'is_active' => $bundle->is_active,
                'items' => $bundle->items->map(fn (BundleItem $i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'variant_id' => $i->variant_id,
                    'quantity' => $i->quantity,
                    'product' => ['name' => $i->product->name],
                ]),
            ],
            'products' => Product::query()
                ->where('is_active', true)
                ->select('id', 'name', 'sku', 'price_cents', 'images')
                ->get(),
        ]);
    }

    public function update(Request $request, Bundle $bundle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:1'],
            'compare_at_price_cents' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bundle->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => $validated['price_cents'],
            'compare_at_price_cents' => $validated['compare_at_price_cents'] ?? null,
            'is_active' => $validated['is_active'] ?? $bundle->is_active,
        ]);

        $bundle->items()->delete();

        foreach ($validated['items'] as $item) {
            $bundle->items()->create([
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('vendor.bundles.index')->with('success', "Bundle \"{$bundle->name}\" updated.");
    }

    public function destroy(Bundle $bundle): RedirectResponse
    {
        $bundle->delete();

        return redirect()->route('vendor.bundles.index')->with('success', 'Bundle deleted.');
    }
}
