<?php

declare(strict_types=1);

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\OrderStateGuard;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Events\OrderStatusUpdated;

final class MarkOrderPaid
{
    public function handle(PaymentSucceeded $event): void
    {
        $order = Order::query()->findOrFail($event->orderId);

        OrderStateGuard::canMarkPaid($order->status);

        $order->update([
            'status' => OrderStatus::Paid,
        ]);

        OrderPaid::dispatch(
            $order->id,
            $order->user_id,
            $order->tenant_id,
            $order->order_number,
            $order->total_cents,
        );

        OrderStatusUpdated::dispatch(
            $order->id,
            $order->user_id,
            $order->tenant_id,
            $order->order_number,
            OrderStatus::Paid->value,
        );
    }
}
