<?php

declare(strict_types=1);

use App\Domain\Chat\Models\Conversation;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

describe('Chat Tenant Isolation', function () {
    it('denies cross-tenant conversation access via API', function () {
        // Tenant A
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        Context::add('tenant_id', $tenantA->id);
        $userA = User::factory()->forTenant($tenantA)->create();
        $convA = Conversation::factory()->create([
            'user_id' => $userA->id,
            'tenant_id' => $tenantA->id,
        ]);

        // Tenant B user trying to access Tenant A conversation
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        Context::add('tenant_id', $tenantB->id);
        $userB = User::factory()->forTenant($tenantB)->create();

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/conversations/{$convA->id}");

        $response->assertNotFound();
    });

    it('denies cross-tenant message posting via API', function () {
        // Tenant A
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a2']);
        Context::add('tenant_id', $tenantA->id);
        $userA = User::factory()->forTenant($tenantA)->create();
        $convA = Conversation::factory()->open()->create([
            'user_id' => $userA->id,
            'tenant_id' => $tenantA->id,
        ]);

        // Tenant B user trying to post to Tenant A conversation
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b2']);
        Context::add('tenant_id', $tenantB->id);
        $userB = User::factory()->forTenant($tenantB)->create();

        Sanctum::actingAs($userB);

        $response = $this->postJson("/api/v1/conversations/{$convA->id}/messages", [
            'body' => 'Cross-tenant attack',
        ]);

        $response->assertNotFound();
    });

    it('allows admin to see their own tenant conversations', function () {
        $tenant = Tenant::factory()->create(['slug' => 'admin-tenant']);
        Context::add('tenant_id', $tenant->id);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Store Administrator']);
        $admin = User::factory()->forTenant($tenant)->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->forTenant($tenant)->create();

        Conversation::factory()->create([
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('super admin can only see support conversations', function () {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['description' => 'Platform Super Administrator']);
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $superAdminRole->id]);

        // Clear tenant context for super admin
        Context::forget('tenant_id');
        Context::forget('tenant');

        // Create a store conversation and a support conversation
        $tenant = Tenant::factory()->create(['slug' => 'super-test-tenant']);
        $customer = User::factory()->forTenant($tenant)->create();

        Context::add('tenant_id', $tenant->id);
        Conversation::factory()->asStore()->create([
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
        ]);
        Context::forget('tenant_id');

        Conversation::factory()->support()->create([
            'user_id' => $customer->id,
            'tenant_id' => null,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertSuccessful();
        // Super admin should only see support conversations
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.type'))->toBe('support');
    });
});
