<?php

declare(strict_types=1);

namespace App\Domain\Refund\Actions;

use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Events\RefundFailed;
use App\Domain\Refund\Events\RefundSucceeded;
use App\Domain\Refund\Models\Refund;
use App\Shared\Domain\AuditLog;
use App\Shared\Domain\DomainEventRecorder;
use DomainException;
use Throwable;

final readonly class RetryRefundExecutionAction
{
    public function __construct(
        private PaymentGatewayService $gateway
    ) {}

    /**
     * Retry a refund that was approved but failed during execution.
     */
    public function execute(Refund $refund): Refund
    {
        if (! in_array($refund->status, [RefundStatus::Approved, RefundStatus::Failed], true)) {
            throw new DomainException(
                "Cannot retry refund in {$refund->status->value} state. Must be approved or failed."
            );
        }

        $refund->update([
            'status' => RefundStatus::Processing,
        ]);

        try {
            $this->gateway->refund(
                paymentIntentId: $refund->payment_intent_id,
                amountCents: $refund->amount_cents,
            );

            $refund->update([
                'status' => RefundStatus::Succeeded,
            ]);

            DomainEventRecorder::record(
                new RefundSucceeded(
                    refundId: $refund->id,
                    orderId: $refund->order_id,
                    amountCents: $refund->amount_cents,
                    currency: $refund->currency,
                )
            );

            AuditLog::log(
                action: 'refund_retry_succeeded',
                targetType: 'refund',
                targetId: $refund->id,
                payload: [
                    'amount_cents' => $refund->amount_cents,
                ],
            );

            return $refund;

        } catch (Throwable $e) {
            $refund->update([
                'status' => RefundStatus::Failed,
            ]);

            DomainEventRecorder::record(
                new RefundFailed(
                    refundId: $refund->id,
                    orderId: $refund->order_id,
                    reason: $e->getMessage(),
                )
            );

            AuditLog::log(
                action: 'refund_retry_failed',
                targetType: 'refund',
                targetId: $refund->id,
                payload: [
                    'error' => $e->getMessage(),
                ],
                result: 'failure',
            );

            throw $e;
        }
    }
}
