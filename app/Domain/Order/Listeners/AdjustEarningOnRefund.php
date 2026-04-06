<?php

declare(strict_types=1);

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderRefunded;
use App\Domain\Order\Models\VendorEarning;
use Illuminate\Contracts\Queue\ShouldQueue;

final class AdjustEarningOnRefund implements ShouldQueue
{
    public function handle(OrderRefunded $event): void
    {
        $earning = VendorEarning::query()
            ->where('order_id', $event->orderId)
            ->first();

        if (! $earning) {
            return;
        }

        $earning->update([
            'refunded_amount_cents' => min($event->refundedAmountCents, $earning->net_amount_cents),
        ]);
    }
}
