<?php

declare(strict_types=1);

namespace App\Domain\Refund\Guards;

use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Models\Refund;
use App\Domain\Shared\Guards\AbstractGuard;

final class RefundProviderConfirmationGuard extends AbstractGuard
{
    public function __construct(
        private readonly Refund $refund,
    ) {}

    public function check(): bool
    {
        // Only completed refunds require provider reference
        if ($this->refund->status !== RefundStatus::Succeeded) {
            return true;
        }

        if (empty($this->refund->provider_reference)) {
            $this->violationMessage = sprintf(
                'Refund %d is marked as SUCCEEDED but has no provider reference',
                $this->refund->id
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
            'refund_id' => $this->refund->id,
            'status' => $this->refund->status->value,
            'has_provider_reference' => ! empty($this->refund->provider_reference),
            'order_id' => $this->refund->order_id,
        ];
    }
}
