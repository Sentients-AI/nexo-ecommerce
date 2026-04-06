<?php

declare(strict_types=1);

namespace App\Domain\Refund\Actions;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateReturnRequestAction
{
    /**
     * @param  array<int, array{order_item_id: int, quantity: int, reason: string}>  $items
     */
    public function execute(Order $order, User $user, array $items, ?string $notes): ReturnRequest
    {
        if (! $order->isRefundable()) {
            throw new DomainException('This order is not eligible for a return.');
        }

        $existingPending = ReturnRequest::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingPending) {
            throw new DomainException('A return request for this order is already in progress.');
        }

        return DB::transaction(function () use ($order, $user, $items, $notes) {
            $returnRequest = ReturnRequest::query()->create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $returnRequest->items()->create([
                    'order_item_id' => $item['order_item_id'],
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'],
                ]);
            }

            return $returnRequest->load('items.orderItem');
        });
    }
}
