<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class LoyaltyPointsEarnedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $points,
        private readonly int $newBalance,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'loyalty_points_earned',
            'points' => $this->points,
            'new_balance' => $this->newBalance,
            'message' => "You earned {$this->points} loyalty points! Your balance is now {$this->newBalance}.",
            'url' => '/en/profile/loyalty',
        ];
    }
}
