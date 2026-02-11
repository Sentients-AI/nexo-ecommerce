<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;

/**
 * Provides tenant-aware query handling for Filament resources.
 *
 * When a super admin has no tenant selected, queries bypass tenant scoping
 * to show all records across all tenants. When a tenant is selected (either
 * by a super admin or for regular tenant users), normal tenant scoping applies.
 */
trait HasTenantAwareness
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        // Super admin with no tenant selected - show all tenants' data
        if ($user?->isSuperAdmin() && ! Context::has('tenant_id')) {
            return $query->withoutTenancy();
        }

        return $query;
    }
}
