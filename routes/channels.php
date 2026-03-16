<?php

declare(strict_types=1);

use App\Domain\Chat\Models\Conversation;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, string $conversationId): bool {
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

Broadcast::channel('orders.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('tenant.{tenantId}.orders', function (User $user, int $tenantId): bool {
    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->isAdmin() && $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.inventory', function (User $user, int $tenantId): bool {
    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->isAdmin() && $user->tenant_id === $tenantId;
});
