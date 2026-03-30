<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Actions;

use App\Domain\Shipping\Models\ShippingMethod;

final class CalculateShippingCostAction
{
    public function execute(?int $shippingMethodId, int $subtotalCents): int
    {
        if ($shippingMethodId === null) {
            return 0;
        }

        $method = ShippingMethod::query()
            ->where('id', $shippingMethodId)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            return 0;
        }

        return $method->calculateCost($subtotalCents);
    }
}
