<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Pages\SuperAdminDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('SuperAdminDashboard access', function (): void {
    it('allows super admin to access dashboard', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role_id' => $role->id,
        ]);

        $this->actingAs($superAdmin);

        expect(SuperAdminDashboard::canAccess())->toBeTrue();
    });

    it('denies regular admin access to dashboard', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        expect(SuperAdminDashboard::canAccess())->toBeFalse();
    });

    it('denies unauthenticated access', function (): void {
        expect(SuperAdminDashboard::canAccess())->toBeFalse();
    });
});
