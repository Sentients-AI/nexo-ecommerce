<?php

declare(strict_types=1);

use App\Domain\Chat\Actions\MarkConversationReadAction;
use App\Domain\Chat\Models\ChatMessage;
use App\Domain\Chat\Models\Conversation;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('MarkConversationReadAction', function () {
    it('marks messages from other senders as read', function () {
        $customer = User::factory()->create();
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $customer->id]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $agent->id,
            'read_at' => null,
        ]);

        app(MarkConversationReadAction::class)->execute($conversation, $customer->id);

        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $agent->id,
            'read_at' => null,
        ]);
    });

    it('does not mark own messages as read', function () {
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $customer->id]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'read_at' => null,
        ]);

        app(MarkConversationReadAction::class)->execute($conversation, $customer->id);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'read_at' => null,
        ]);
    });

    it('skips already-read messages', function () {
        $customer = User::factory()->create();
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $customer->id]);
        $readAt = now()->subMinutes(5);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $agent->id,
            'read_at' => $readAt,
        ]);

        app(MarkConversationReadAction::class)->execute($conversation, $customer->id);

        // Count total messages - should be 1 (the one we created)
        expect(ChatMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
    });
});
