<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Actions\MarkConversationReadAction;
use App\Domain\Chat\Actions\SendMessageAction;
use App\Domain\Chat\DTOs\SendMessageData;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Shared\Enums\ErrorCode;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreChatMessageRequest;
use App\Http\Resources\Api\V1\ChatMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageController extends Controller
{
    public function __construct(
        private readonly SendMessageAction $sendMessage,
        private readonly MarkConversationReadAction $markRead,
    ) {}

    public function store(StoreChatMessageRequest $request, int $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findAccessibleConversation($user, $conversationId);

        if ($conversation === null) {
            return $this->errorResponse(ErrorCode::ConversationNotFound, 'Conversation not found.');
        }

        if ($conversation->isClosed()) {
            return $this->errorResponse(ErrorCode::ConversationClosed, 'This conversation is closed.');
        }

        $message = $this->sendMessage->execute(new SendMessageData(
            conversationId: $conversation->id,
            senderId: $user->id,
            body: $request->string('body')->toString(),
        ));

        return response()->json(
            ['message' => new ChatMessageResource($message)],
            201
        );
    }

    public function markRead(Request $request, int $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findAccessibleConversation($user, $conversationId);

        if ($conversation === null) {
            return $this->errorResponse(ErrorCode::ConversationNotFound, 'Conversation not found.');
        }

        $this->markRead->execute($conversation, $user->id);

        return response()->json(['success' => true]);
    }

    private function findAccessibleConversation(User $user, int $id): ?Conversation
    {
        $user->loadMissing('role');

        $conversation = Conversation::query()->withoutTenancy()->find($id);

        if ($conversation === null) {
            return null;
        }

        if ($conversation->user_id === $user->id) {
            return $conversation;
        }

        if ($user->isSuperAdmin() && $conversation->isSupportConversation()) {
            return $conversation;
        }

        if ($user->isAdmin() && $conversation->tenant_id === $user->tenant_id) {
            return $conversation;
        }

        return null;
    }

    private function errorResponse(ErrorCode $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'retryable' => $code->isRetryable(),
            ],
        ], $code->httpStatus());
    }
}
