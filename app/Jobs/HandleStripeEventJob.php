<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Payment\Actions\ConfirmPaymentIntentAction;
use App\Domain\Payment\Events\PaymentFailed;
use App\Domain\Payment\Models\PaymentIntent;
use App\Shared\Domain\DomainEventRecorder;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

final class HandleStripeEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private $event
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        match ($this->event->type) {
            'payment_intent.succeeded' => $this->handleSuccess(),
            'payment_intent.payment_failed' => $this->handleFailure(),
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChange(),
            default => null,
        };
    }

    private function handleSuccess(): void
    {
        $intent = PaymentIntent::query()
            ->where('provider_reference', $this->event->data->object->id)
            ->first();

        if (! $intent || $intent->status->isTerminal()) {
            return; // idempotent - already processed or not found
        }

        app(ConfirmPaymentIntentAction::class)->execute($intent);
    }

    private function handleSubscriptionChange(): void
    {
        $stripeSubscription = $this->event->data->object;

        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'stripe_status' => $stripeSubscription->status,
            'ends_at' => $stripeSubscription->cancel_at
                ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)
                : null,
        ]);
    }

    private function handleFailure(): void
    {
        $intent = PaymentIntent::query()
            ->where('provider_reference', $this->event->data->object->id)
            ->first();

        if (! $intent || $intent->status->isTerminal()) {
            return; // idempotent - already processed or not found
        }

        DB::transaction(function () use ($intent): void {
            $failureMessage = $this->event->data->object->last_payment_error->message ?? 'Payment failed';

            $intent->markAsFailed([
                'error' => $failureMessage,
                'stripe_event_id' => $this->event->id,
            ]);

            // Update order status to Failed
            $intent->order->update([
                'status' => OrderStatus::Failed,
            ]);

            DomainEventRecorder::record(
                new PaymentFailed(
                    paymentIntentId: $intent->id,
                    orderId: $intent->order_id,
                    reason: $failureMessage,
                )
            );
        });
    }
}
