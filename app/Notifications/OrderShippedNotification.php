<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Order\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderShippedNotification extends Notification implements ShouldQueue
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
        $channels = ['mail', 'database', 'broadcast'];
        if (filled($notifiable->phone_number ?? null) && ($notifiable->sms_notifications_enabled ?? false)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        return "Your order #{$this->order->order_number} has shipped via {$this->order->carrier}. Track: ".url('/en/track');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orderUrl = url("/en/orders/{$this->order->id}");

        $message = (new MailMessage)
            ->subject("Your order #{$this->order->order_number} has shipped!")
            ->greeting("Hi {$notifiable->name},")
            ->line('Great news — your order is on its way!')
            ->line("**Carrier:** {$this->order->carrier}")
            ->line("**Tracking Number:** {$this->order->tracking_number}");

        if ($this->order->estimated_delivery_at) {
            $message->line('**Estimated Delivery:** '.$this->order->estimated_delivery_at->format('F j, Y'));
        }

        $message
            ->action('Track Your Order', $orderUrl)
            ->line('Thank you for shopping with us!');

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_shipped',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'carrier' => $this->order->carrier,
            'tracking_number' => $this->order->tracking_number,
            'message' => "Your order #{$this->order->order_number} has shipped via {$this->order->carrier}.",
            'url' => "/en/orders/{$this->order->id}",
        ];
    }
}
