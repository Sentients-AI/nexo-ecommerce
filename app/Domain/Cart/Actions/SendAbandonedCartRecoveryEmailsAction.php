<?php

declare(strict_types=1);

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Models\Cart;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Support\Carbon;

final class SendAbandonedCartRecoveryEmailsAction
{
    public function execute(int $idleHours = 24): int
    {
        $threshold = Carbon::now()->subHours($idleHours);

        $carts = Cart::query()
            ->whereNotNull('user_id')
            ->whereNull('completed_at')
            ->whereNull('recovery_email_sent_at')
            ->where('updated_at', '<=', $threshold)
            ->whereHas('items')
            ->with(['user', 'items.product'])
            ->get();

        $sent = 0;

        foreach ($carts as $cart) {
            if ($cart->user === null || $cart->user->email === null) {
                continue;
            }

            $cart->user->notify(new AbandonedCartNotification($cart));

            $cart->update(['recovery_email_sent_at' => now()]);

            $sent++;
        }

        return $sent;
    }
}
