<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Bundle\Models\Bundle;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class BundleController extends Controller
{
    public function index(): Response
    {
        $bundles = Bundle::query()
            ->where('is_active', true)
            ->with('items.product')
            ->latest()
            ->get()
            ->map(fn (Bundle $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'description' => $b->description,
                'price_cents' => $b->price_cents,
                'compare_at_price_cents' => $b->compare_at_price_cents,
                'savings_percent' => $b->savings_percent,
                'images' => $b->images ?? [],
                'items' => $b->items->map(fn ($i) => [
                    'product' => ['name' => $i->product->name, 'images' => $i->product->images],
                    'quantity' => $i->quantity,
                ]),
            ]);

        return Inertia::render('Bundles/Index', [
            'bundles' => $bundles,
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $bundle = Bundle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('items.product.stock', 'items.variant')
            ->firstOrFail();

        $inStock = $bundle->items->every(function ($item): bool {
            $stock = $item->variant?->stock ?? $item->product?->stock;

            return $stock && $stock->quantity_available >= $item->quantity;
        });

        return Inertia::render('Bundles/Show', [
            'bundle' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'price_cents' => $bundle->price_cents,
                'compare_at_price_cents' => $bundle->compare_at_price_cents,
                'savings_percent' => $bundle->savings_percent,
                'images' => $bundle->images ?? [],
                'in_stock' => $inStock,
                'items' => $bundle->items->map(fn ($item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'images' => $item->product->images,
                        'price_cents' => $item->product->price_cents,
                        'sale_price' => $item->product->sale_price,
                    ],
                    'variant' => $item->variant ? [
                        'id' => $item->variant->id,
                        'sku' => $item->variant->sku,
                    ] : null,
                ]),
            ],
        ]);
    }
}
