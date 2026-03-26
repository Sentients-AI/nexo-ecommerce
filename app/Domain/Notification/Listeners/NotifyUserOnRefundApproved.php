<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Events\RefundApproved;
use App\Notifications\RefundApprovedNotification;

final class NotifyUserOnRefundApproved
{
    public function handle(RefundApproved $event): void
    {
        $order = Order::find($event->orderId);

        if (! $order?->user) {
            return;
        }

        $order->user->notify(new RefundApprovedNotification(
            orderId: $event->orderId,
            amountCents: $event->amountCents,
            currency: $event->currency,
        ));
    }
}
