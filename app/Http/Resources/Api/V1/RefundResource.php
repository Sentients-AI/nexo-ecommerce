<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'created_at' => $this->created_at->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
        ];
    }
}
