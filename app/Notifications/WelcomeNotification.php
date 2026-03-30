<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shopUrl = url('/en');

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').'!')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Your account has been created successfully.')
            ->line('You can now browse our products, track your orders, earn loyalty points, and more.')
            ->action('Start Shopping', $shopUrl)
            ->line('If you have any questions, feel free to reach out via our live chat.');
    }
}
