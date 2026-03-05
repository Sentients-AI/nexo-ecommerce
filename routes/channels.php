<?php

declare(strict_types=1);

use App\Domain\Chat\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId): bool {
    $conversation = Conversation::query()->withoutTenancy()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    if ($conversation->user_id === $user->id) {
        return true;
    }

    if ($user->isSuperAdmin() && $conversation->isSupportConversation()) {
        return true;
    }

    if ($user->isAdmin() && $conversation->tenant_id === $user->tenant_id) {
        return true;
    }

    return false;
});
