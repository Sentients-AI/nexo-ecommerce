<?php

declare(strict_types=1);

namespace App\Domain\Order\Guards;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shared\Guards\AbstractGuard;

final class OrderPaymentRequiredGuard extends AbstractGuard
{
    public function __construct(
        private readonly Order $order,
    ) {}

    public function check(): bool
    {
        // Only paid orders require a payment intent
        if ($this->order->status !== OrderStatus::Paid) {
            return true;
        }

        if ($this->order->paymentIntent === null) {
            $this->violationMessage = sprintf(
                'Order %s is marked as PAID but has no payment intent',
                $this->order->order_number
            );

            return false;
        }

        return true;
    }

    protected function getEntityType(): string
    {
        return 'Order';
    }

    protected function getEntityId(): int
    {
        return $this->order->id;
    }

    protected function getContext(): array
    {
        return [
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'has_payment_intent' => $this->order->paymentIntent !== null,
        ];
    }
}
