<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Order\Events\OrderCancelled;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderRefunded;
use App\Domain\User\Models\User;
use App\Notifications\OrderStatusChangedNotification;

final class NotifyUserOnOrderStatusChange
{
    public function handle(OrderPaid|OrderCancelled|OrderRefunded $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $status = match (true) {
            $event instanceof OrderPaid => 'paid',
            $event instanceof OrderCancelled => 'cancelled',
            $event instanceof OrderRefunded => 'refunded',
        };

        $user->notify(new OrderStatusChangedNotification(
            orderId: $event->orderId,
            orderNumber: $event->orderNumber,
            status: $status,
        ));
    }
}
