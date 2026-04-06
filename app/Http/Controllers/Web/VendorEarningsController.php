<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Enums\EarningStatus;
use App\Domain\Order\Models\VendorEarning;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class VendorEarningsController extends Controller
{
    public function index(): Response
    {
        // Promote pending earnings that have passed the hold period
        VendorEarning::query()
            ->where('status', EarningStatus::Pending)
            ->where('available_at', '<=', now())
            ->update(['status' => EarningStatus::Available]);

        $pendingCents = (int) VendorEarning::query()
            ->where('status', EarningStatus::Pending)
            ->sum('net_amount_cents');

        $availableCents = (int) VendorEarning::query()
            ->where('status', EarningStatus::Available)
            ->selectRaw('SUM(net_amount_cents - refunded_amount_cents) as total')
            ->value('total');

        $paidOutCents = (int) VendorEarning::query()
            ->where('status', EarningStatus::PaidOut)
            ->sum('net_amount_cents');

        $thisMonthCents = (int) VendorEarning::query()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->selectRaw('SUM(net_amount_cents - refunded_amount_cents) as total')
            ->value('total');

        $earnings = VendorEarning::query()
            ->with('order:id,order_number,total_cents,created_at')
            ->latest()
            ->paginate(25)
            ->through(fn (VendorEarning $e) => [
                'id' => $e->id,
                'order_number' => $e->order?->order_number,
                'gross_amount_cents' => $e->gross_amount_cents,
                'platform_fee_cents' => $e->platform_fee_cents,
                'net_amount_cents' => $e->net_amount_cents,
                'refunded_amount_cents' => $e->refunded_amount_cents,
                'effective_net_cents' => $e->effectiveNetCents(),
                'status' => $e->status->value,
                'available_at' => $e->available_at?->toDateString(),
                'paid_out_at' => $e->paid_out_at?->toDateString(),
                'created_at' => $e->created_at->toDateString(),
            ]);

        $platformFeeRate = config('earnings.platform_fee_rate', 0.02);

        return Inertia::render('Vendor/Earnings', [
            'stats' => [
                'pending_cents' => $pendingCents,
                'available_cents' => $availableCents,
                'paid_out_cents' => $paidOutCents,
                'this_month_cents' => $thisMonthCents,
            ],
            'earnings' => $earnings,
            'platform_fee_rate' => $platformFeeRate,
        ]);
    }
}
