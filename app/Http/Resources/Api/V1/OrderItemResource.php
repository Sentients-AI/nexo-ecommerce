<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'price_cents' => $this->price_cents_snapshot,
            'tax_cents' => $this->tax_cents_snapshot,
            'name_snapshot' => $this->product?->name ?? 'Unknown Product',
        ];
    }
}
