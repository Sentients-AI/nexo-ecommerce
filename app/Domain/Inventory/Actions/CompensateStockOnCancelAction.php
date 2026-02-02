<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Shared\Domain\AuditLog;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CompensateStockOnCancelAction
{
    /**
     * Release reserved stock when an order is cancelled.
     * This compensates for the reservation made during checkout.
     */
    public function execute(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::Cancelled], true)) {
            throw new DomainException(
                "Cannot compensate stock for order in {$order->status->value} state."
            );
        }

        DB::transaction(function () use ($order): void {
            $order->load('items');

            foreach ($order->items as $item) {
                $stock = Stock::query()
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock === null) {
                    continue;
                }

                $releaseQuantity = min($item->quantity, $stock->quantity_reserved);

                if ($releaseQuantity <= 0) {
                    continue;
                }

                $stock->increment('quantity_available', $releaseQuantity);
                $stock->decrement('quantity_reserved', $releaseQuantity);

                StockMovement::query()->create([
                    'stock_id' => $stock->id,
                    'product_id' => $item->product_id,
                    'type' => StockMovementType::Release,
                    'quantity' => $releaseQuantity,
                    'reference_type' => 'order_cancellation',
                    'reference_id' => $order->id,
                    'reason' => 'Stock released due to order cancellation',
                ]);
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
            ]);

            AuditLog::log(
                action: 'stock_compensated_for_cancellation',
                targetType: 'order',
                targetId: $order->id,
                payload: [
                    'items_count' => $order->items->count(),
                ],
            );
        });
    }
}
