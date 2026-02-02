<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'client_secret' => $this->when(
                $this->status->canBeConfirmed(),
                $this->provider_reference
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
