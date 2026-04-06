<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Events\StockFellBelowThreshold;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyVendorOnLowStock implements ShouldQueue
{
    public function handle(StockFellBelowThreshold $event): void
    {
        if (! config('inventory.low_stock_notifications')) {
            return;
        }

        $product = Product::query()->find($event->productId);

        if (! $product) {
            return;
        }

        $admins = User::query()
            ->where('tenant_id', $event->tenantId)
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new LowStockNotification($product, $event->newQuantity, $event->threshold));
        }
    }
}
