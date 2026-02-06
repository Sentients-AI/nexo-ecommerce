<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;

final class PromotionPolicy
{
    /**
     * Determine whether the user can view any promotions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can view the promotion.
     */
    public function view(User $user): bool
    {
        return $user->hasAnyRole(['support', 'finance', 'admin']);
    }

    /**
     * Determine whether the user can create promotions.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'finance']);
    }

    /**
     * Determine whether the user can update the promotion.
     */
    public function update(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'finance']);
    }

    /**
     * Determine whether the user can delete the promotion.
     */
    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
