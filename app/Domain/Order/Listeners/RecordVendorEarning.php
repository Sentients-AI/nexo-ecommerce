<?php

declare(strict_types=1);

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\VendorEarning;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RecordVendorEarning implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->orderId);

        if (! $order) {
            return;
        }

        $feeRate = (float) config('earnings.platform_fee_rate', 0.02);
        $holdDays = (int) config('earnings.payout_hold_days', 7);

        $gross = (int) $order->total_cents;
        $fee = (int) round($order->subtotal_cents * $feeRate);
        $net = $gross - $fee;

        VendorEarning::query()->create([
            'tenant_id' => $event->tenantId,
            'order_id' => $event->orderId,
            'gross_amount_cents' => $gross,
            'platform_fee_cents' => $fee,
            'net_amount_cents' => $net,
            'refunded_amount_cents' => 0,
            'status' => 'pending',
            'available_at' => now()->addDays($holdDays),
        ]);
    }
}
