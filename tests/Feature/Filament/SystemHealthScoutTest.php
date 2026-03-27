<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Pages\SystemHealth;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
    // Force collection driver so tests do not require a live Typesense server.
    config(['scout.driver' => 'collection']);
});

describe('SystemHealth access', function (): void {
    it('allows admin to access the page', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        $this->actingAs($admin);

        expect(SystemHealth::canAccess())->toBeTrue();
    });

    it('denies guests access to the page', function (): void {
        expect(SystemHealth::canAccess())->toBeFalse();
    });
});

describe('Scout re-index actions', function (): void {
    it('renders the re-index header actions for an admin', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        Context::add('tenant_id', $tenant->id);
        $this->actingAs($admin);

        Livewire::test(SystemHealth::class)
            ->assertSuccessful()
            ->assertActionExists('reindex_search')
            ->assertActionExists('reindex_search_fresh');
    });

    it('re-indexes search when reindex_search action is called', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        Context::add('tenant_id', $tenant->id);
        $this->actingAs($admin);

        Livewire::test(SystemHealth::class)
            ->callAction(TestAction::make('reindex_search'))
            ->assertHasNoErrors()
            ->assertNotified('Search index updated');
    });

    it('fresh re-indexes search when reindex_search_fresh action is called', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        Context::add('tenant_id', $tenant->id);
        $this->actingAs($admin);

        Livewire::test(SystemHealth::class)
            ->callAction(TestAction::make('reindex_search_fresh'))
            ->assertHasNoErrors()
            ->assertNotified('Search index rebuilt');
    });
});
