<?php

declare(strict_types=1);

use App\Domain\Chat\Actions\SendMessageAction;
use App\Domain\Chat\DTOs\SendMessageData;
use App\Domain\Chat\Models\ChatMessage;
use App\Domain\Chat\Models\Conversation;
use App\Events\MessageSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    Event::fake();
});

describe('SendMessageAction', function () {
    it('creates a chat message', function () {
        $conversation = Conversation::factory()->create();

        $action = app(SendMessageAction::class);
        $message = $action->execute(new SendMessageData(
            conversationId: $conversation->id,
            senderId: $conversation->user_id,
            body: 'Hello, I need help.',
        ));

        expect($message)->toBeInstanceOf(ChatMessage::class)
            ->and($message->conversation_id)->toBe($conversation->id)
            ->and($message->sender_id)->toBe($conversation->user_id)
            ->and($message->body)->toBe('Hello, I need help.');
    });

    it('updates last_message_at on the conversation', function () {
        $conversation = Conversation::factory()->create([
            'last_message_at' => now()->subHour(),
        ]);

        $before = $conversation->last_message_at;

        app(SendMessageAction::class)->execute(new SendMessageData(
            conversationId: $conversation->id,
            senderId: $conversation->user_id,
            body: 'Test message',
        ));

        $conversation->refresh();

        expect($conversation->last_message_at->isAfter($before))->toBeTrue();
    });

    it('dispatches the MessageSent event', function () {
        $conversation = Conversation::factory()->create();

        app(SendMessageAction::class)->execute(new SendMessageData(
            conversationId: $conversation->id,
            senderId: $conversation->user_id,
            body: 'Event test',
        ));

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->body === 'Event test');
    });
});
