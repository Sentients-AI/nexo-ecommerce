<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Pages\AdvancedAnalytics;
use App\Filament\Widgets\AdvancedAnalyticsStatsWidget;
use App\Filament\Widgets\RevenueTrendWidget;
use App\Filament\Widgets\TopProductsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
    Role::factory()->create(['name' => 'customer']);

    $this->tenant = Tenant::factory()->create();
    Context::add('tenant_id', $this->tenant->id);
});

function makeSuperAdminUser(): User
{
    $role = Role::where('name', 'super_admin')->first();

    return User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
}

function makeAdminUser(Tenant $tenant): User
{
    $role = Role::where('name', 'admin')->first();

    return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
}

function makeCustomerUser(Tenant $tenant): User
{
    $role = Role::where('name', 'customer')->first();

    return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
}

describe('AdvancedAnalytics access', function (): void {
    it('allows super admin to access', function (): void {
        $this->actingAs(makeSuperAdminUser());

        expect(AdvancedAnalytics::canAccess())->toBeTrue();
    });

    it('allows admin to access', function (): void {
        $this->actingAs(makeAdminUser($this->tenant));

        expect(AdvancedAnalytics::canAccess())->toBeTrue();
    });

    it('denies customers', function (): void {
        $this->actingAs(makeCustomerUser($this->tenant));

        expect(AdvancedAnalytics::canAccess())->toBeFalse();
    });

    it('denies unauthenticated users', function (): void {
        expect(AdvancedAnalytics::canAccess())->toBeFalse();
    });
});

describe('AdvancedAnalytics widgets', function (): void {
    it('registers the expected widget classes', function (): void {
        $this->actingAs(makeSuperAdminUser());

        $page = new AdvancedAnalytics();

        expect($page->getWidgets())
            ->toContain(AdvancedAnalyticsStatsWidget::class)
            ->toContain(RevenueTrendWidget::class)
            ->toContain(TopProductsWidget::class);
    });
});
