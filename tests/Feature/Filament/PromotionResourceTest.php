<?php

declare(strict_types=1);

use App\Domain\Promotion\Models\Promotion;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Resources\Promotions\PromotionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('PromotionResource record resolution', function (): void {
    it('super admin without tenant selected can resolve a promotion with no tenant', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin);

        // Promotion created without tenant context (tenant_id = null)
        $promotion = Promotion::withoutTenancy()->create([
            'name' => 'Global Promo',
            'tenant_id' => null,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'scope' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $resolved = PromotionResource::resolveRecordRouteBinding($promotion->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($promotion->id);
    });

    it('super admin with a tenant selected can still resolve a promotion with no tenant', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($superAdmin);

        // Simulate super admin having selected a tenant
        Context::add('tenant_id', $tenant->id);

        // Promotion created without tenant context (tenant_id = null)
        $promotion = Promotion::withoutTenancy()->create([
            'name' => 'Global Promo',
            'tenant_id' => null,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'scope' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $resolved = PromotionResource::resolveRecordRouteBinding($promotion->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($promotion->id);
    });

    it('super admin with a tenant selected can resolve a promotion belonging to that tenant', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($superAdmin);
        Context::add('tenant_id', $tenant->id);

        $promotion = Promotion::withoutTenancy()->create([
            'name' => 'Tenant Promo',
            'tenant_id' => $tenant->id,
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'scope' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $resolved = PromotionResource::resolveRecordRouteBinding($promotion->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($promotion->id);
    });

    it('super admin can resolve a promotion belonging to a different tenant than currently selected', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAs($superAdmin);
        // Super admin is viewing as tenant A
        Context::add('tenant_id', $tenantA->id);

        // Promotion belongs to tenant B
        $promotion = Promotion::withoutTenancy()->create([
            'name' => 'Tenant B Promo',
            'tenant_id' => $tenantB->id,
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'scope' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $resolved = PromotionResource::resolveRecordRouteBinding($promotion->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($promotion->id);
    });
});
