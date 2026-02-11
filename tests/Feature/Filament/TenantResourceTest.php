<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Resources\Tenants\TenantResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('TenantResource access', function (): void {
    it('allows super admin to access tenant resource', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);

        $this->actingAs($superAdmin);

        expect(TenantResource::canAccess())->toBeTrue();
    });

    it('denies regular admin access to tenant resource', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        expect(TenantResource::canAccess())->toBeFalse();
    });

    it('denies unauthenticated access', function (): void {
        expect(TenantResource::canAccess())->toBeFalse();
    });
});

describe('TenantResource View As action', function (): void {
    it('sets session when switching to tenant via view as action', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->actingAs($superAdmin);

        // The View As action stores tenant_id in session
        session(['filament_selected_tenant_id' => $tenant->id]);

        expect(session('filament_selected_tenant_id'))->toBe($tenant->id);
    });
});
