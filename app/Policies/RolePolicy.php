<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;

final class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin can view roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     * Admin can view roles.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * No direct creation via control plane.
     */
    public function create(): bool
    {
        return false;
    }

    /**
     * No direct editing via control plane.
     */
    public function update(): bool
    {
        return false;
    }

    /**
     * No deletion allowed.
     */
    public function delete(): bool
    {
        return false;
    }
}
