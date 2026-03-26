<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Cart $cart) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cartUrl = url('/en/cart');
        $itemLines = $this->cart->items->map(function (CartItem $item): string {
            $name = $item->product?->name ?? 'Product';
            $price = number_format($item->price_cents_snapshot / 100, 2);

            return "• {$name} × {$item->quantity} — \${$price}";
        })->implode("\n");

        $message = (new MailMessage)
            ->subject('You left something in your cart!')
            ->greeting("Hi {$notifiable->name},")
            ->line('You have items waiting in your cart. Come back and complete your purchase!')
            ->line($itemLines ?: 'Your cart has items waiting for you.')
            ->action('Return to Cart', $cartUrl)
            ->line('This is a one-time reminder. We hope to see you soon!');

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'cart_id' => $this->cart->id,
        ];
    }
}
