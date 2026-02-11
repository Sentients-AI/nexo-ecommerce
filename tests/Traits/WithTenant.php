<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Context;

trait WithTenant
{
    protected Tenant $tenant;

    /**
     * Set up a tenant for the test.
     */
    protected function setUpTenant(?array $attributes = []): Tenant
    {
        $this->tenant = Tenant::factory()->create($attributes);

        Context::add('tenant_id', $this->tenant->id);
        Context::add('tenant', $this->tenant);

        return $this->tenant;
    }

    /**
     * Create and authenticate a user within the current tenant.
     */
    protected function actingAsUserInTenant(?array $userAttributes = []): User
    {
        if (! isset($this->tenant)) {
            $this->setUpTenant();
        }

        $user = User::factory()
            ->forTenant($this->tenant)
            ->create($userAttributes);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Set tenant context without authentication.
     */
    protected function withTenantContext(Tenant $tenant): self
    {
        Context::add('tenant_id', $tenant->id);
        Context::add('tenant', $tenant);

        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Clear tenant context.
     */
    protected function clearTenantContext(): self
    {
        Context::forget('tenant_id');
        Context::forget('tenant');

        return $this;
    }
}
