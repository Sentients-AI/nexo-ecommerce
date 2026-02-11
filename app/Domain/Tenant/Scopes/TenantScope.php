<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Context;

final class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = Context::get('tenant_id');

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }

    /**
     * Extend the query builder with the needed macros.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenancy', fn (Builder $builder): Builder => $builder->withoutGlobalScope(self::class));
    }
}
