<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'formatted_discount' => $this->formatted_discount,
            'scope' => $this->scope->value,
            'auto_apply' => $this->auto_apply,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'minimum_order_cents' => $this->minimum_order_cents,
            'maximum_discount_cents' => $this->maximum_discount_cents,
            'is_active' => $this->is_active,
        ];
    }
}
