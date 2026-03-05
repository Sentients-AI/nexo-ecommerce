<?php

declare(strict_types=1);

namespace App\Domain\Chat\Actions;

use App\Domain\Chat\DTOs\CreateConversationData;
use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Models\Conversation;
use Illuminate\Support\Facades\DB;

final class CreateConversationAction
{
    public function execute(CreateConversationData $data): Conversation
    {
        return DB::transaction(function () use ($data): Conversation {
            // Use withoutEvents so BelongsToTenant::creating does not override
            // an explicit null tenant_id (needed for platform support conversations).
            $conversation = Conversation::withoutEvents(fn () => Conversation::query()->create([
                'user_id' => $data->userId,
                'type' => $data->type,
                'status' => ConversationStatus::Open,
                'subject' => $data->subject,
                'tenant_id' => $data->tenantId,
                'last_message_at' => now(),
            ]));

            $conversation->messages()->create([
                'sender_id' => $data->userId,
                'body' => $data->initialMessage,
            ]);

            return $conversation->load('messages.sender', 'latestMessage');
        });
    }
}
