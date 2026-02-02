<?php

declare(strict_types=1);

namespace App\Domain\Payment\Guards;

use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Shared\Guards\AbstractGuard;

final class PaymentAmountGuard extends AbstractGuard
{
    public function __construct(
        private readonly PaymentIntent $paymentIntent,
    ) {}

    public function check(): bool
    {
        $order = $this->paymentIntent->order;

        if ($order === null) {
            $this->violationMessage = 'Payment intent has no associated order';

            return false;
        }

        if ($this->paymentIntent->amount > $order->total_cents) {
            $this->violationMessage = sprintf(
                'Payment amount (%d) exceeds order total (%d)',
                $this->paymentIntent->amount,
                $order->total_cents
            );

            return false;
        }

        return true;
    }

    protected function getEntityType(): string
    {
        return 'PaymentIntent';
    }

    protected function getEntityId(): int
    {
        return $this->paymentIntent->id;
    }

    protected function getContext(): array
    {
        return [
            'payment_amount' => $this->paymentIntent->amount,
            'order_total' => $this->paymentIntent->order?->total_cents,
            'order_id' => $this->paymentIntent->order_id,
        ];
    }
}
