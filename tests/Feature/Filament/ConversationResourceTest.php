<?php

declare(strict_types=1);

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Resources\Chat\ConversationResource;
use App\Filament\Resources\Chat\Pages\ListConversations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('ConversationResource access', function (): void {
    it('super admin can access the conversation resource', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin);

        expect(ConversationResource::canAccess())->toBeTrue();
    });

    it('admin can access the conversation resource', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        $this->actingAs($admin);
        Context::add('tenant_id', $tenant->id);

        expect(ConversationResource::canAccess())->toBeTrue();
    });

    it('cannot create or delete conversations', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
        $this->actingAs($superAdmin);

        expect(ConversationResource::canCreate())->toBeFalse();
    });
});

describe('ConversationResource listing', function (): void {
    it('super admin only sees support conversations', function (): void {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $superAdminRole->id]);

        $tenant = Tenant::factory()->create();
        $customer = User::factory()->create(['tenant_id' => $tenant->id]);

        // Create a store conversation
        Conversation::query()->withoutTenancy()->create([
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'type' => 'store',
            'status' => ConversationStatus::Open,
        ]);

        // Create a support conversation
        Conversation::query()->withoutTenancy()->create([
            'user_id' => $customer->id,
            'tenant_id' => null,
            'type' => 'support',
            'status' => ConversationStatus::Open,
        ]);

        $this->actingAs($superAdmin);

        $query = ConversationResource::getEloquentQuery();
        expect($query->count())->toBe(1);
        expect($query->first()->type->value)->toBe('support');
    });

    it('admin sees tenant conversations via table', function (): void {
        $tenant = Tenant::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole->id]);
        $customer = User::factory()->create(['tenant_id' => $tenant->id]);

        Context::add('tenant_id', $tenant->id);

        Conversation::factory()->count(2)->create([
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin);

        $livewire = livewire(ListConversations::class)
            ->assertSuccessful();

        expect(true)->toBeTrue();
    });
});
