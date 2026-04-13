<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Order\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        $channels = ['mail', 'database', 'broadcast'];
        if (filled($notifiable->phone_number ?? null) && ($notifiable->sms_notifications_enabled ?? false)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        return "Order #{$this->order->order_number} confirmed! Total: "
            .mb_strtoupper((string) $this->order->currency).' '
            .number_format($this->order->total_cents / 100, 2)
            .'. Track at '.url('/en/track');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->load('items.product', 'items.variant');
        $orderUrl = url("/en/orders/{$order->id}");
        $currency = mb_strtoupper((string) $order->currency);
        $total = number_format($order->total_cents / 100, 2);

        $name = $notifiable instanceof AnonymousNotifiable
            ? ($order->guest_name ?? 'Customer')
            : $notifiable->name;

        $message = (new MailMessage)
            ->subject("Order #{$order->order_number} Confirmed!")
            ->greeting("Hi {$name},")
            ->line("Thank you for your order! We've received it and will start processing it right away.")
            ->line("**Order:** #{$order->order_number}")
            ->line('**Items:**');

        foreach ($order->items as $item) {
            $name = $item->variant?->name ?? $item->product->name;
            $lineTotal = number_format($item->price_cents_snapshot * $item->quantity / 100, 2);
            $message->line("- {$item->quantity}x {$name} — {$currency} {$lineTotal}");
        }

        if ($order->shipping_address) {
            $addr = $order->shipping_address;
            $message->line('**Ships to:** '.implode(', ', array_filter([
                $addr['line1'] ?? null,
                $addr['city'] ?? null,
                $addr['country'] ?? null,
            ])));
        }

        if ($order->discount_cents > 0) {
            $discount = number_format($order->discount_cents / 100, 2);
            $message->line("**Discount:** -{$currency} {$discount}");
        }

        $message
            ->line("**Total:** {$currency} {$total}")
            ->action('View Order', $orderUrl)
            ->line('We will notify you once your order ships.');

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_confirmed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_cents' => $this->order->total_cents,
            'currency' => $this->order->currency,
            'message' => "Order #{$this->order->order_number} has been confirmed.",
            'url' => "/en/orders/{$this->order->id}",
        ];
    }
}
