<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DownloadReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $tokens  [order_item_id => plain_token]
     */
    public function __construct(
        private readonly int $orderId,
        private readonly string $orderNumber,
        private readonly array $tokens,
        private readonly int $expiryHours,
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
        $mail = (new MailMessage)
            ->subject("Your downloads for order #{$this->orderNumber} are ready")
            ->greeting("Hi {$notifiable->name}!")
            ->line('Thank you for your purchase. Your downloadable files are ready.')
            ->line("Each link expires in {$this->expiryHours} hours and can be used up to ".config('downloads.max_downloads', 5).' times.');

        foreach ($this->tokens as $token) {
            $url = url("/downloads/{$token}");
            $mail->action('Download your file', $url);
        }

        return $mail->line('If you have any issues accessing your files, please contact support.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'download_ready',
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'message' => "Your downloads for order #{$this->orderNumber} are ready.",
            'url' => "/en/orders/{$this->orderId}",
            'token_count' => count($this->tokens),
        ];
    }
}
