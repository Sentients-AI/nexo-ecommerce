<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Traits;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Tenant\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Context;

/**
 * @property int|null $tenant_id
 * @property-read Tenant|null $tenant
 */
trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (! array_key_exists('tenant_id', $model->getAttributes())) {
                $model->tenant_id = Context::get('tenant_id');
            }
        });
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
