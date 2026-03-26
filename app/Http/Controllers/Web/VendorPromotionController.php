<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Promotion\Models\Promotion;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class VendorPromotionController extends Controller
{
    public function index(): Response
    {
        $promotions = Promotion::query()
            ->withCount('usages')
            ->latest()
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'discount_type' => $p->discount_type->value,
                'discount_value' => $p->discount_value,
                'scope' => $p->scope,
                'auto_apply' => $p->auto_apply,
                'is_active' => $p->is_active,
                'is_flash_sale' => $p->is_flash_sale,
                'buy_quantity' => $p->buy_quantity,
                'get_quantity' => $p->get_quantity,
                'tiers' => $p->tiers,
                'time_remaining_seconds' => $p->timeRemainingSeconds(),
                'usage_count' => $p->usage_count,
                'usage_limit' => $p->usage_limit,
                'usages_count' => $p->usages_count,
                'minimum_order_cents' => $p->minimum_order_cents,
                'maximum_discount_cents' => $p->maximum_discount_cents,
                'starts_at' => $p->starts_at?->toIso8601String(),
                'ends_at' => $p->ends_at?->toIso8601String(),
                'formatted_discount' => $p->formatted_discount,
                'is_valid' => $p->isValid(),
            ]);

        $activeCount = $promotions->where('is_valid', true)->count();
        $expiredCount = $promotions->where('is_active', true)->where('is_valid', false)->count();

        return Inertia::render('Vendor/Promotions', [
            'promotions' => $promotions,
            'active_count' => $activeCount,
            'expired_count' => $expiredCount,
        ]);
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return redirect()->back()->with('success', $promotion->is_active
            ? "Promotion \"{$promotion->name}\" enabled."
            : "Promotion \"{$promotion->name}\" disabled."
        );
    }
}
