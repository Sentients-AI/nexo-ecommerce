<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;

final class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin can manage categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     * Admin can manage categories.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can edit categories.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can delete categories.
     */
    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
