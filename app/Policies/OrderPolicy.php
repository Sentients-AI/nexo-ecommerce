<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;

final class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     * Support, Finance, and Admin can view orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     * Owner can view their own orders. Support, Finance, and Admin can view all.
     */
    public function view(User $user, Order $order): bool
    {
        if ($order->user_id === $user->id) {
            return true;
        }

        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can request a refund on an order.
     * Only the owner can request refunds, and only if the order is refundable.
     */
    public function requestRefund(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->isRefundable();
    }

    /**
     * No direct creation allowed in control plane.
     */
    public function create(): bool
    {
        return false;
    }

    /**
     * No direct editing allowed in control plane.
     */
    public function update(): bool
    {
        return false;
    }

    /**
     * No deletion allowed in control plane.
     */
    public function delete(): bool
    {
        return false;
    }

    /**
     * Cancel order action.
     * Support and Admin can cancel orders.
     */
    public function cancel(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin']);
    }

    /**
     * Retry payment action.
     * Support and Admin can retry payments.
     */
    public function retryPayment(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin']);
    }

    /**
     * Mark as fraudulent action.
     * Admin only.
     */
    public function markAsFraudulent(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
