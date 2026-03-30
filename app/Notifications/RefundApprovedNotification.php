<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RefundApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly int $amountCents,
        private readonly string $currency,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $formatted = number_format($this->amountCents / 100, 2);
        $currency = mb_strtoupper($this->currency);
        $orderUrl = url("/en/orders/{$this->orderId}");

        return (new MailMessage)
            ->subject('Your Refund Has Been Approved')
            ->greeting("Hi {$notifiable->name},")
            ->line("Great news — your refund of **{$currency} {$formatted}** has been approved and is being processed.")
            ->line('Refunds typically appear on your original payment method within 5–10 business days.')
            ->action('View Order', $orderUrl)
            ->line('Thank you for your patience.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $formatted = number_format($this->amountCents / 100, 2);

        return [
            'type' => 'refund_approved',
            'order_id' => $this->orderId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'message' => "Your refund of {$this->currency} {$formatted} has been approved.",
            'url' => "/en/orders/{$this->orderId}",
        ];
    }
}
