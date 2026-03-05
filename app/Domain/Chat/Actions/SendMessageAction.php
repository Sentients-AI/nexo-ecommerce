<?php

declare(strict_types=1);

namespace App\Domain\Chat\Actions;

use App\Domain\Chat\DTOs\SendMessageData;
use App\Domain\Chat\Models\ChatMessage;
use App\Domain\Chat\Models\Conversation;
use App\Events\MessageSent;

final class SendMessageAction
{
    public function execute(SendMessageData $data): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'conversation_id' => $data->conversationId,
            'sender_id' => $data->senderId,
            'body' => $data->body,
        ]);

        Conversation::query()->withoutTenancy()->where('id', $data->conversationId)->update([
            'last_message_at' => now(),
        ]);

        MessageSent::dispatch($message->load('sender'));

        return $message;
    }
}
