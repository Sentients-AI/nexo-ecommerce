<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Tenant\Scopes\TenantScope;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class StoreController extends Controller
{
    public function show(string $locale, string $slug): Response
    {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($tenant === null) {
            abort(404);
        }

        $products = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['category', 'stock'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->orderByDesc('created_at')
            ->paginate(12);

        $totalReviews = Review::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('is_approved', true)
            ->count();

        $avgRating = Review::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('is_approved', true)
            ->avg('rating');

        return Inertia::render('Stores/Show', [
            'store' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'description' => $tenant->description,
                'total_products' => $products->total(),
                'total_reviews' => $totalReviews,
                'average_rating' => $avgRating ? round((float) $avgRating, 1) : null,
            ],
            'products' => $products,
        ]);
    }
}
