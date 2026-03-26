<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorInventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $lowStock = $request->boolean('low_stock');

        $products = Product::query()
            ->with([
                'stock:id,product_id,variant_id,quantity_available,quantity_reserved',
                'variants' => fn ($q) => $q->with('stock:id,product_id,variant_id,quantity_available,quantity_reserved')
                    ->orderBy('sort_order'),
                'variants.attributeValues.attributeType',
            ])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($lowStock, function ($q) {
                $q->whereHas('stock', fn ($s) => $s->where('quantity_available', '<=', 5))
                    ->orWhereHas('variants', fn ($v) => $v->whereHas(
                        'stock',
                        fn ($s) => $s->where('quantity_available', '<=', 5)
                    ));
            })
            ->latest()
            ->paginate(25)
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => $p->stock ? [
                    'id' => $p->stock->id,
                    'quantity_available' => $p->stock->quantity_available,
                    'quantity_reserved' => $p->stock->quantity_reserved,
                ] : null,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'attributes' => $v->attributeValues->map(fn ($av) => [
                        'type' => $av->attributeType->name,
                        'value' => $av->value,
                    ]),
                    'stock' => $v->stock ? [
                        'id' => $v->stock->id,
                        'quantity_available' => $v->stock->quantity_available,
                        'quantity_reserved' => $v->stock->quantity_reserved,
                    ] : null,
                ]),
            ]);

        $lowStockCount = Stock::query()
            ->where('quantity_available', '<=', 5)
            ->where('quantity_available', '>', 0)
            ->count();

        $outOfStockCount = Stock::query()
            ->where('quantity_available', '<=', 0)
            ->count();

        return Inertia::render('Vendor/Inventory', [
            'products' => $products,
            'search' => $search,
            'low_stock_filter' => $lowStock,
            'stats' => [
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
        ]);
    }

    public function update(Request $request, Stock $stock): RedirectResponse
    {
        $validated = $request->validate([
            'quantity_available' => ['required', 'integer', 'min:0'],
        ]);

        $stock->update(['quantity_available' => $validated['quantity_available']]);

        return back()->with('success', 'Stock updated.');
    }
}
