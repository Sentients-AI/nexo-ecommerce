<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Product\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Product $product,
        private readonly int $quantity,
        private readonly int $threshold,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inventoryUrl = url('/vendor/inventory');

        return (new MailMessage)
            ->subject("Low stock alert: {$this->product->name}")
            ->greeting('Low stock warning')
            ->line("**{$this->product->name}** is running low — only **{$this->quantity}** unit(s) remaining (threshold: {$this->threshold}).")
            ->action('Manage Inventory', $inventoryUrl)
            ->line('Replenish stock soon to avoid missing sales.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => $this->quantity,
            'threshold' => $this->threshold,
        ];
    }
}
