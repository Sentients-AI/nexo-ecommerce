<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Events\StockReplenished;
use App\Domain\Inventory\Models\WaitlistSubscription;
use App\Domain\Product\Models\Product;
use App\Notifications\BackInStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

final class NotifyWaitlistSubscribers implements ShouldQueue
{
    public function handle(StockReplenished $event): void
    {
        $product = Product::query()->find($event->productId);

        if (! $product) {
            return;
        }

        $subscriptions = WaitlistSubscription::query()
            ->where('product_id', $event->productId)
            ->whereNull('notified_at')
            ->get();

        foreach ($subscriptions as $subscription) {
            (new AnonymousNotifiable)
                ->route('mail', $subscription->email)
                ->notify(new BackInStockNotification($product));

            $subscription->update(['notified_at' => now()]);
        }
    }
}
