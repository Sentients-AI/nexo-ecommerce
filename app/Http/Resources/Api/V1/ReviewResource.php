<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'created_at' => $this->created_at->toISOString(),
            'photos' => ReviewPhotoResource::collection($this->whenLoaded('photos')),
            'replies' => ReviewReplyResource::collection($this->whenLoaded('replies')),
            'helpful_count' => $this->whenLoaded('votes', fn () => $this->votes->where('is_helpful', true)->count()),
            'not_helpful_count' => $this->whenLoaded('votes', fn () => $this->votes->where('is_helpful', false)->count()),
            'user_vote' => $this->when(
                $this->relationLoaded('votes') && auth()->check(),
                fn () => $this->votes->firstWhere('user_id', auth()->id())?->is_helpful
            ),
        ];
    }
}
