<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\PaymentIntent;
use App\Shared\Metrics\MetricsRecorder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ConfirmPaymentIntentAction
{
    public function __construct(
        private PaymentGatewayService $gateway
    ) {}

    public function execute(PaymentIntent $intent): PaymentIntent
    {
        if (! $intent->status->canBeConfirmed()) {
            throw new DomainException('Payment intent cannot be confirmed from current state.');
        }

        $startTime = microtime(true);

        return DB::transaction(function () use ($intent, $startTime): PaymentIntent {
            $intent->increment('attempts');

            try {
                $response = $this->gateway->confirmIntent($intent);

                $intent->update([
                    'status' => PaymentStatus::Succeeded,
                ]);

                $latencyMs = (microtime(true) - $startTime) * 1000;
                MetricsRecorder::histogram('payment_confirmation_latency_ms', $latencyMs);
                MetricsRecorder::increment('payments_succeeded_total', ['currency' => $intent->currency]);

            } catch (Throwable $e) {
                $intent->update([
                    'status' => PaymentStatus::Failed,
                ]);

                MetricsRecorder::increment('payments_failed_total', [
                    'currency' => $intent->currency,
                    'reason' => 'gateway_error',
                ]);

                throw $e;
            }

            return $intent;
        });
    }
}
