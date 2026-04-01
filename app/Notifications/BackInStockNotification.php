<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Product\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BackInStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Product $product) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productUrl = url("/en/products/{$this->product->slug}");

        return (new MailMessage)
            ->subject("{$this->product->name} is back in stock!")
            ->greeting('Good news!')
            ->line("**{$this->product->name}** is back in stock and available to order.")
            ->action('Shop Now', $productUrl)
            ->line('Grab it before it sells out again.');
    }
}
