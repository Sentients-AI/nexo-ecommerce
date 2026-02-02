<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;

final class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     * All control plane users can view products.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     * All control plane users can view products.
     */
    public function view(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Admin can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can edit products.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can delete products.
     */
    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Change price action.
     * Admin only can change prices.
     */
    public function changePrice(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Schedule price change action.
     * Admin only can schedule price changes.
     */
    public function schedulePriceChange(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
