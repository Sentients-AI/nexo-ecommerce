<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->superAdminRole = Role::factory()->create(['name' => 'super_admin']);
    $this->customerRole = Role::factory()->create(['name' => 'customer']);

    $this->superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->superAdminRole->id]);

    $this->tenant = Tenant::factory()->create();
    $this->targetUser = User::factory()->forTenant($this->tenant)->create(['role_id' => $this->customerRole->id]);
});

describe('impersonation start', function (): void {
    it('allows super admin to impersonate a tenant user', function (): void {
        $this->actingAs($this->superAdmin)
            ->post("/impersonation/{$this->targetUser->id}/start")
            ->assertRedirect('/en');

        expect(session('impersonating_as'))->toBe($this->targetUser->id)
            ->and(session('original_admin_id'))->toBe($this->superAdmin->id)
            ->and(auth()->id())->toBe($this->targetUser->id);
    });

    it('forbids non-super-admin from impersonating', function (): void {
        $regularUser = User::factory()->forTenant($this->tenant)->create(['role_id' => $this->customerRole->id]);

        $this->actingAs($regularUser)
            ->post("/impersonation/{$this->targetUser->id}/start")
            ->assertForbidden();
    });

    it('forbids impersonating another super admin', function (): void {
        $anotherSuperAdmin = User::factory()->superAdmin()->create(['role_id' => $this->superAdminRole->id]);

        $this->actingAs($this->superAdmin)
            ->post("/impersonation/{$anotherSuperAdmin->id}/start")
            ->assertForbidden();
    });

    it('requires authentication', function (): void {
        $this->post("/impersonation/{$this->targetUser->id}/start")
            ->assertRedirect();
    });
});

describe('impersonation stop', function (): void {
    it('restores original admin session and redirects to admin panel', function (): void {
        // Start impersonation
        $this->actingAs($this->superAdmin)
            ->post("/impersonation/{$this->targetUser->id}/start");

        // Now stop it
        $this->post('/impersonation/stop')
            ->assertRedirect('/control-plane');

        expect(session('impersonating_as'))->toBeNull()
            ->and(session('original_admin_id'))->toBeNull()
            ->and(auth()->id())->toBe($this->superAdmin->id);
    });

    it('stop works even with no active impersonation session', function (): void {
        $this->actingAs($this->superAdmin)
            ->post('/impersonation/stop')
            ->assertRedirect('/control-plane');
    });
});

describe('impersonation inertia props', function (): void {
    it('shares impersonation state as inactive when not impersonating', function (): void {
        $this->actingAs($this->targetUser)
            ->get('/en')
            ->assertInertia(fn ($page) => $page
                ->where('impersonation.active', false)
                ->where('impersonation.original_admin_name', null)
            );
    });

    it('shares active impersonation state including original admin name', function (): void {
        $this->actingAs($this->superAdmin)
            ->post("/impersonation/{$this->targetUser->id}/start");

        $this->get('/en')
            ->assertInertia(fn ($page) => $page
                ->where('impersonation.active', true)
                ->where('impersonation.original_admin_name', $this->superAdmin->name)
            );
    });
});
