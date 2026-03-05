<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'subject' => $this->subject,
            'tenant_id' => $this->tenant_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => new ChatMessageResource($this->latestMessage)),
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'unread_count' => $this->when(
                isset($this->unread_count),
                fn () => $this->unread_count
            ),
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
