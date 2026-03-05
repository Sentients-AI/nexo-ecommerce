<?php

declare(strict_types=1);

namespace App\Domain\Chat\Actions;

use App\Domain\Chat\Models\Conversation;

final class MarkConversationReadAction
{
    public function execute(Conversation $conversation, int $readerId): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $readerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
