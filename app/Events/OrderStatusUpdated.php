<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly ?int $userId,
        public readonly ?int $tenantId,
        public readonly string $orderNumber,
        public readonly string $status,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->userId !== null) {
            $channels[] = new PrivateChannel('orders.'.$this->userId);
        }

        if ($this->tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$this->tenantId.'.orders');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'status' => $this->status,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
