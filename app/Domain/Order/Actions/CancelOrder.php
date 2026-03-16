<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Inventory\Actions\ReleaseStockAction as ReleaseStock;
use App\Domain\Inventory\DTOs\ReserveStockData;
use App\Domain\Order\Events\OrderCancelled;
use App\Domain\Order\Models\Order;
use App\Events\OrderStatusUpdated;
use Exception;
use Illuminate\Support\Facades\DB;

final readonly class CancelOrder
{
    public function __construct(
        private ReleaseStock $releaseStock,
    ) {}

    /**
     * Execute the action to cancel an order.
     *
     * @throws Exception
     */
    public function execute(Order $order): Order
    {
        if ($order->isCancelled()) {
            throw new Exception('Order is already cancelled');
        }

        if ($order->isCompleted()) {
            throw new Exception('Cannot cancel a completed order');
        }

        return DB::transaction(function () use ($order) {
            // Release reserved stock
            foreach ($order->items as $item) {
                $this->releaseStock->execute(new ReserveStockData(
                    productId: $item->product_id,
                    quantity: $item->quantity,
                    referenceId: $order->id,
                    referenceType: Order::class,
                ));
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
            ]);

            $fresh = $order->fresh();

            OrderCancelled::dispatch(
                $fresh->id,
                $fresh->user_id,
                $fresh->tenant_id,
                $fresh->order_number,
            );

            OrderStatusUpdated::dispatch(
                $fresh->id,
                $fresh->user_id,
                $fresh->tenant_id,
                $fresh->order_number,
                'cancelled',
            );

            return $fresh;
        });
    }
}
