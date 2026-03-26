<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Loyalty\Events\PointsEarned;
use App\Domain\User\Models\User;
use App\Notifications\LoyaltyPointsEarnedNotification;

final class NotifyUserOnLoyaltyPointsEarned
{
    public function handle(PointsEarned $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $user->notify(new LoyaltyPointsEarnedNotification(
            points: $event->points,
            newBalance: $event->newBalance,
        ));
    }
}
