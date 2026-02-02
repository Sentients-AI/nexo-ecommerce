<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Inventory\Models\Stock;
use App\Domain\User\Models\User;

final class StockPolicy
{
    /**
     * Determine whether the user can view any models.
     * Support, Finance, and Admin can view stock.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     * Support, Finance, and Admin can view stock.
     */
    public function view(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
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
     * Adjust stock action.
     * Admin only can adjust stock.
     */
    public function adjust(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Reconcile stock action.
     * Admin only can reconcile stock.
     */
    public function reconcile(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
