<?php

declare(strict_types=1);

use App\Domain\Chat\Models\Conversation;
use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Conversation List API', function () {
    it('requires authentication', function () {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    });

    it('returns only conversations belonging to authenticated user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Conversation::factory()->count(2)->create(['user_id' => $user->id]);
        Conversation::factory()->count(3)->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('admin sees all conversations for their tenant', function () {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()?->id]);

        // Create users with conversations in same tenant
        $user1 = User::factory()->forTenant($this->tenant)->create();
        $user2 = User::factory()->forTenant($this->tenant)->create();
        Conversation::factory()->create(['user_id' => $user1->id, 'tenant_id' => $this->tenant->id]);
        Conversation::factory()->create(['user_id' => $user2->id, 'tenant_id' => $this->tenant->id]);

        // Make admin belong to tenant
        $admin->update(['tenant_id' => $this->tenant->id]);
        // Give admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Store Administrator']);
        $admin->update(['role_id' => $adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('returns conversations with correct structure', function () {
        $user = User::factory()->create();
        Conversation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'status', 'subject', 'tenant_id', 'last_message_at', 'created_at'],
            ],
        ]);
    });
});

describe('Conversation Create API', function () {
    it('requires authentication', function () {
        $this->postJson('/api/v1/conversations', [])->assertUnauthorized();
    });

    it('creates a store conversation', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/conversations', [
            'type' => 'store',
            'initial_message' => 'I have an issue with my order.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('conversation.type', 'store');
        $response->assertJsonPath('conversation.status', 'open');

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'type' => 'store',
        ]);
    });

    it('creates a support conversation', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/conversations', [
            'type' => 'support',
            'initial_message' => 'I need platform help.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('conversation.type', 'support');
        $this->assertDatabaseHas('conversations', ['type' => 'support', 'tenant_id' => null]);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/conversations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'initial_message']);
    });

    it('validates type must be store or support', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/conversations', [
            'type' => 'invalid',
            'initial_message' => 'Test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });
});

describe('Conversation Show API', function () {
    it('requires authentication', function () {
        $this->getJson('/api/v1/conversations/1')->assertUnauthorized();
    });

    it('returns 404 for non-existent conversation', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/conversations/99999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CONVERSATION_NOT_FOUND');
    });

    it('returns 404 for conversation owned by another user', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertNotFound();
    });

    it('returns conversation for owner', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('conversation.id', $conversation->id);
    });
});

describe('Conversation Close API', function () {
    it('requires authentication', function () {
        $this->patchJson('/api/v1/conversations/1/close')->assertUnauthorized();
    });

    it('closes an open conversation', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/conversations/{$conversation->id}/close");

        $response->assertSuccessful();
        $response->assertJsonPath('conversation.status', 'closed');
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'status' => 'closed']);
    });

    it('returns 404 for inaccessible conversation', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/conversations/{$conversation->id}/close")
            ->assertNotFound();
    });
});
