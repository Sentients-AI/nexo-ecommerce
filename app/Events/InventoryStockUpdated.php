<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryStockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $productId,
        public readonly ?int $tenantId,
        public readonly int $quantityAvailable,
        public readonly int $quantityReserved,
        public readonly string $changeType,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('product.'.$this->productId)];

        if ($this->tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$this->tenantId.'.inventory');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity_available' => $this->quantityAvailable,
            'quantity_reserved' => $this->quantityReserved,
            'quantity_in_stock' => $this->quantityAvailable - $this->quantityReserved,
            'change_type' => $this->changeType,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
