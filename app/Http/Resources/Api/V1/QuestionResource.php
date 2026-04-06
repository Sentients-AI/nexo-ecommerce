<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_answered' => $this->is_answered,
            'author_name' => $this->user?->name ?? 'Anonymous',
            'created_at' => $this->created_at->toISOString(),
            'answers' => QuestionAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
