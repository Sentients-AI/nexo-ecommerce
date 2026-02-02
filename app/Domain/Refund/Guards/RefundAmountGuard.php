<?php

declare(strict_types=1);

namespace App\Domain\Refund\Guards;

use App\Domain\Refund\Models\Refund;
use App\Domain\Shared\Guards\AbstractGuard;

final class RefundAmountGuard extends AbstractGuard
{
    public function __construct(
        private readonly Refund $refund,
    ) {}

    public function check(): bool
    {
        $order = $this->refund->order;

        if ($order === null) {
            $this->violationMessage = 'Refund has no associated order';

            return false;
        }

        $totalRefunded = $order->refunded_amount_cents ?? 0;
        $proposedTotal = $totalRefunded + $this->refund->amount_cents;

        if ($proposedTotal > $order->total_cents) {
            $this->violationMessage = sprintf(
                'Total refunds (%d) would exceed order total (%d)',
                $proposedTotal,
                $order->total_cents
            );

            return false;
        }

        return true;
    }

    protected function getEntityType(): string
    {
        return 'Refund';
    }

    protected function getEntityId(): int
    {
        return $this->refund->id;
    }

    protected function getContext(): array
    {
        return [
            'refund_amount' => $this->refund->amount_cents,
            'current_refunded' => $this->refund->order?->refunded_amount_cents ?? 0,
            'order_total' => $this->refund->order?->total_cents,
            'order_id' => $this->refund->order_id,
        ];
    }
}
