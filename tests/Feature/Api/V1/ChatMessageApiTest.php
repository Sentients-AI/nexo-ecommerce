<?php

declare(strict_types=1);

use App\Domain\Chat\Models\ChatMessage;
use App\Domain\Chat\Models\Conversation;
use App\Domain\User\Models\User;
use App\Events\MessageSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Send Message API', function () {
    it('requires authentication', function () {
        $this->postJson('/api/v1/conversations/1/messages', [])->assertUnauthorized();
    });

    it('sends a message to an open conversation', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Hello, I need help with my order.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message.body', 'Hello, I need help with my order.');
        $response->assertJsonPath('message.sender_id', $user->id);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => 'Hello, I need help with my order.',
        ]);
    });

    it('rejects sending to a closed conversation', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->closed()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Can I reopen this?',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONVERSATION_CLOSED');
    });

    it('rejects unauthorized users', function () {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Unauthorized message',
        ])->assertNotFound();
    });

    it('validates body is required', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('validates body max length', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => str_repeat('a', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('dispatches MessageSent event', function () {
        Event::fake();

        $user = User::factory()->create();
        $conversation = Conversation::factory()->open()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Event test',
        ])->assertCreated();

        Event::assertDispatched(MessageSent::class);
    });
});

describe('Mark Read API', function () {
    it('requires authentication', function () {
        $this->postJson('/api/v1/conversations/1/read')->assertUnauthorized();
    });

    it('marks messages as read', function () {
        $customer = User::factory()->create();
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $customer->id]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $agent->id,
            'read_at' => null,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/read");

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $agent->id,
            'read_at' => null,
        ]);
    });

    it('returns 404 for inaccessible conversation', function () {
        $user = User::factory()->create();
        $otherConv = Conversation::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$otherConv->id}/read")
            ->assertNotFound();
    });
});
