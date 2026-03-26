<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\DTOs\ShipOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Notifications\OrderShippedNotification;
use Exception;
use Illuminate\Support\Facades\DB;

final readonly class ShipOrderAction
{
    /**
     * Mark an order as shipped with tracking information.
     *
     * @throws Exception
     */
    public function execute(Order $order, ShipOrderData $data): Order
    {
        if ($order->status === OrderStatus::Shipped) {
            throw new Exception('Order has already been shipped.');
        }

        if (! $order->status->canTransitionTo(OrderStatus::Shipped)) {
            throw new Exception("Cannot transition order from '{$order->status->value}' to 'shipped'.");
        }

        return DB::transaction(function () use ($order, $data): Order {
            $order->update([
                'status' => OrderStatus::Shipped,
                'carrier' => $data->carrier,
                'tracking_number' => $data->trackingNumber,
                'shipped_at' => now(),
                'estimated_delivery_at' => $data->estimatedDeliveryAt,
            ]);

            $fresh = $order->fresh(['user']);

            OrderStatusUpdated::dispatch(
                $fresh->id,
                $fresh->user_id,
                $fresh->tenant_id,
                $fresh->order_number,
                OrderStatus::Shipped->value,
            );

            if ($fresh->user) {
                $fresh->user->notify(new OrderShippedNotification($fresh));
            }

            return $fresh;
        });
    }
}
