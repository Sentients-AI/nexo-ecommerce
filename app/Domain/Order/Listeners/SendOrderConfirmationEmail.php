<?php

declare(strict_types=1);

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderCreated;
use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use App\Notifications\OrderConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

final class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        $order = Order::query()->find($event->orderId);

        if (! $order) {
            return;
        }

        // Authenticated user order
        if ($order->user_id !== null) {
            $user = User::query()->find($event->userId);

            if ($user) {
                $user->notify(new OrderConfirmedNotification($order));
            }

            return;
        }

        // Guest order — send to guest_email
        if ($order->guest_email) {
            (new AnonymousNotifiable)
                ->route('mail', $order->guest_email)
                ->notify(new OrderConfirmedNotification($order));
        }
    }
}
