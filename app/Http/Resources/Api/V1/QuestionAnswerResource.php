<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuestionAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_vendor_answer' => $this->is_vendor_answer,
            'author_name' => $this->user?->name ?? 'Anonymous',
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
