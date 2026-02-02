<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class FailPaymentIntentAction
{
    public function __construct(
        private PaymentGatewayService $gateway
    ) {}

    public function execute(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->status->isTerminal()) {
            return $intent;
        }

        return DB::transaction(function () use ($intent): PaymentIntent {

            try {
                $this->gateway->cancelIntent($intent);
            } catch (Throwable) {
            }

            $intent->update([
                'status' => PaymentStatus::Failed,
            ]);

            return $intent;
        });
    }
}
