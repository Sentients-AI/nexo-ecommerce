<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;

final class SystemConfigPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin can view system configurations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     * Admin can view system configurations.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * No direct creation via UI.
     */
    public function create(): bool
    {
        return false;
    }

    /**
     * No direct editing via UI.
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

    /**
     * Update value action.
     * Admin only can update config values.
     */
    public function updateValue(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
