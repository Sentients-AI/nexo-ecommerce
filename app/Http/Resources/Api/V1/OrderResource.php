<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'shipping_cost_cents' => $this->shipping_cost_cents,
            'total_cents' => $this->total_cents,
            'refunded_amount_cents' => $this->refunded_amount_cents ?? 0,
            'currency' => $this->currency,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment_intent' => new PaymentIntentResource($this->whenLoaded('paymentIntent')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
