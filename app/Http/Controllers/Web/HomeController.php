<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Promotion\Models\Promotion;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $now = now();

        $flashSales = Promotion::query()
            ->where('is_flash_sale', true)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with(['products' => fn ($q) => $q->where('is_active', true)->with('stock')->limit(4)])
            ->orderBy('ends_at')
            ->limit(2)
            ->get()
            ->map(fn (Promotion $promo): array => [
                'id' => $promo->id,
                'name' => $promo->name,
                'discount_type' => $promo->discount_type->value,
                'discount_value' => $promo->discount_value,
                'ends_at' => $promo->ends_at?->toIso8601String(),
                'seconds_remaining' => $promo->timeRemainingSeconds(),
                'products' => $promo->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price_cents' => $p->price_cents,
                    'image' => $p->images[0] ?? null,
                    'in_stock' => $p->stock?->isInStock() ?? false,
                    'discounted_price_cents' => $promo->discount_type->value === 'percentage'
                        ? (int) round($p->price_cents * (1 - $promo->discount_value / 10000))
                        : max(0, $p->price_cents - (int) $promo->discount_value),
                ]),
            ]);

        return Inertia::render('Home', [
            'flashSales' => $flashSales,
        ]);
    }
}
