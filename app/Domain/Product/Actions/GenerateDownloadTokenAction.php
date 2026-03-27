<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\ProductDownload;
use App\Notifications\DownloadReadyNotification;
use Illuminate\Support\Facades\Context;

final readonly class GenerateDownloadTokenAction
{
    /**
     * Generate download tokens for all downloadable items in a paid order
     * and notify the customer.
     *
     * Returns an array of plain tokens keyed by order_item_id so the
     * notification can build download URLs without a second DB read.
     *
     * @return array<int, string> [order_item_id => plain_token]
     */
    public function execute(int $orderId): array
    {
        $order = Order::query()
            ->with(['items.product', 'user'])
            ->findOrFail($orderId);

        Context::add('tenant_id', $order->tenant_id);

        $maxDownloads = config('downloads.max_downloads', 5);
        $expiryHours = config('downloads.expiry_hours', 48);

        $tokens = [];

        foreach ($order->items as $item) {
            if (! $item->product?->is_downloadable) {
                continue;
            }
            if (! $item->product->download_file_path) {
                continue;
            }
            $plain = bin2hex(random_bytes(32));

            ProductDownload::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'user_id' => $order->user_id,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addHours($expiryHours),
                'max_downloads' => $maxDownloads,
                'download_count' => 0,
            ]);

            $tokens[$item->id] = $plain;
        }

        if ($tokens !== [] && $order->user) {
            $order->user->notify(new DownloadReadyNotification(
                orderId: $order->id,
                orderNumber: $order->order_number,
                tokens: $tokens,
                expiryHours: $expiryHours,
            ));
        }

        return $tokens;
    }
}
