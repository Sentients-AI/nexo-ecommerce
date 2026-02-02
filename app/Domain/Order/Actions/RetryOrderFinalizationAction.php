<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Shared\Domain\AuditLog;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RetryOrderFinalizationAction
{
    /**
     * Retry order finalization when payment succeeded but order wasn't updated.
     * This handles the race condition where payment webhook arrives but order
     * status update failed.
     */
    public function execute(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $paymentIntent = $order->paymentIntent;

            if ($paymentIntent === null) {
                throw new DomainException('Order has no associated payment intent.');
            }

            if ($paymentIntent->status !== PaymentStatus::Succeeded) {
                throw new DomainException('Payment intent has not succeeded.');
            }

            if ($order->status === OrderStatus::Paid) {
                return $order;
            }

            $previousStatus = $order->status;

            $order->update([
                'status' => OrderStatus::Paid,
            ]);

            AuditLog::log(
                action: 'order_finalization_retried',
                targetType: 'order',
                targetId: $order->id,
                payload: [
                    'previous_status' => $previousStatus->value,
                    'new_status' => OrderStatus::Paid->value,
                    'payment_intent_id' => $paymentIntent->id,
                ],
            );

            return $order->fresh();
        });
    }
}
