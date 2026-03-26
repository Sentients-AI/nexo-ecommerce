<?php

declare(strict_types=1);

namespace App\Domain\Order\DTOs;

use App\Shared\DTOs\BaseData;
use Carbon\Carbon;

final class ShipOrderData extends BaseData
{
    public function __construct(
        public string $carrier,
        public string $trackingNumber,
        public ?Carbon $estimatedDeliveryAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            carrier: $data['carrier'],
            trackingNumber: $data['tracking_number'],
            estimatedDeliveryAt: isset($data['estimated_delivery_at'])
                ? Carbon::parse($data['estimated_delivery_at'])
                : null,
        );
    }
}
