<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Events\StockFellBelowThreshold;
use App\Domain\Inventory\Events\StockReplenished;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\StockMovement;
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
                'stock' => fn ($q) => $q->with(['movements' => fn ($m) => $m->latest()->limit(10)]),
                'variants' => fn ($q) => $q->with([
                    'stock' => fn ($sq) => $sq->with(['movements' => fn ($m) => $m->latest()->limit(10)]),
                ])->orderBy('sort_order'),
                'variants.attributeValues.attributeType',
            ])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($lowStock, function ($q) {
                $threshold = config('inventory.low_stock_threshold');
                $q->whereHas('stock', fn ($s) => $s->where('quantity_available', '<=', $threshold))
                    ->orWhereHas('variants', fn ($v) => $v->whereHas(
                        'stock',
                        fn ($s) => $s->where('quantity_available', '<=', $threshold)
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
                    'movements' => $p->stock->movements->map(fn (StockMovement $m) => [
                        'type' => $m->type->value,
                        'quantity' => $m->quantity,
                        'reason' => $m->reason,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
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
                        'movements' => $v->stock->movements->map(fn (StockMovement $m) => [
                            'type' => $m->type->value,
                            'quantity' => $m->quantity,
                            'reason' => $m->reason,
                            'created_at' => $m->created_at?->toIso8601String(),
                        ]),
                    ] : null,
                ]),
            ]);

        $threshold = config('inventory.low_stock_threshold');

        $lowStockCount = Stock::query()
            ->where('quantity_available', '<=', $threshold)
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

        $threshold = (int) config('inventory.low_stock_threshold');
        $wasOutOfStock = ! $stock->isInStock();
        $wasAboveThreshold = $stock->quantity_available > $threshold;
        $quantityChange = $validated['quantity_available'] - $stock->quantity_available;

        $stock->update(['quantity_available' => $validated['quantity_available']]);

        if ($quantityChange !== 0) {
            StockMovement::query()->create([
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'type' => $quantityChange > 0 ? StockMovementType::In : StockMovementType::Out,
                'quantity' => abs($quantityChange),
                'reason' => 'Vendor manual adjustment',
                'user_id' => $request->user()?->id,
            ]);
        }

        $fresh = $stock->fresh();

        if ($wasOutOfStock && $fresh?->isInStock()) {
            StockReplenished::dispatch(
                $stock->product_id,
                (int) $stock->tenant_id,
                $validated['quantity_available'],
            );
        }

        $newQuantity = $validated['quantity_available'];
        if ($wasAboveThreshold && $newQuantity > 0 && $newQuantity <= $threshold) {
            StockFellBelowThreshold::dispatch(
                $stock->product_id,
                (int) $stock->tenant_id,
                $newQuantity,
                $threshold,
            );
        }

        return back()->with('success', 'Stock updated.');
    }
}
