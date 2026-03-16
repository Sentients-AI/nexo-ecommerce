<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductPriceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $productId,
        public readonly int $newPriceCents,
        public readonly ?int $newSalePrice,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('product.'.$this->productId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'price.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'price_cents' => $this->newPriceCents,
            'sale_price' => $this->newSalePrice,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
