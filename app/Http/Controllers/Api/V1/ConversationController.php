<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Actions\CreateConversationAction;
use App\Domain\Chat\DTOs\CreateConversationData;
use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Shared\Enums\ErrorCode;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ConversationController extends Controller
{
    public function __construct(private readonly CreateConversationAction $createConversation) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        $query = Conversation::query()
            ->with(['user', 'latestMessage.sender'])
            ->orderByDesc('last_message_at');

        if ($user->isSuperAdmin()) {
            $query->withoutTenancy()->where('type', ConversationType::Support);
        } elseif ($user->isAdmin()) {
            // Admin sees all conversations for their tenant (default tenant scope applies)
        } else {
            // Regular users see only their own conversations
            $query->where('user_id', $user->id);
        }

        return ConversationResource::collection($query->paginate(20));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        $type = $request->string('type')->toString();
        $tenantId = $type === ConversationType::Support->value ? null : $user->tenant_id;

        $data = new CreateConversationData(
            userId: $user->id,
            type: $type,
            subject: $request->string('subject')->toString() ?: null,
            initialMessage: $request->string('initial_message')->toString(),
            tenantId: $tenantId,
        );

        $conversation = $this->createConversation->execute($data);

        return response()->json(
            ['conversation' => new ConversationResource($conversation->load('user', 'messages.sender', 'latestMessage'))],
            201
        );
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findAccessibleConversation($user, $conversationId);

        if (! $conversation instanceof Conversation) {
            return $this->errorResponse(ErrorCode::ConversationNotFound, 'Conversation not found.');
        }

        $conversation->load('user', 'messages.sender');

        return response()->json([
            'conversation' => new ConversationResource($conversation),
        ]);
    }

    public function close(Request $request, int $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findAccessibleConversation($user, $conversationId);

        if (! $conversation instanceof Conversation) {
            return $this->errorResponse(ErrorCode::ConversationNotFound, 'Conversation not found.');
        }

        $conversation->update(['status' => ConversationStatus::Closed]);

        return response()->json([
            'conversation' => new ConversationResource($conversation->fresh()),
        ]);
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
