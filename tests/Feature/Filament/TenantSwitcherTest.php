<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Livewire\TenantSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('TenantSwitcher component', function (): void {
    it('renders for super admin', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(TenantSwitcher::class)
            ->assertSuccessful()
            ->assertSee('All Tenants');
    });

    it('shows active tenants in dropdown', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);
        Tenant::factory()->create([
            'name' => 'Active Tenant',
            'is_active' => true,
        ]);
        Tenant::factory()->create([
            'name' => 'Inactive Tenant',
            'is_active' => false,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(TenantSwitcher::class)
            ->assertSee('Active Tenant')
            ->assertDontSee('Inactive Tenant');
    });

    it('displays current selection', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin);
        session(['filament_selected_tenant_id' => $tenant->id]);

        Livewire::test(TenantSwitcher::class)
            ->assertSee('Test Tenant');
    });
});
