<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shipping\Models\ShippingMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShippingMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subtotalCents = (int) $request->query('subtotal_cents', 0);

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('rate_cents')
            ->get()
            ->map(fn (ShippingMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'description' => $method->description,
                'type' => $method->type->value,
                'rate_cents' => $method->rate_cents,
                'cost_cents' => $method->calculateCost($subtotalCents),
                'min_order_cents' => $method->min_order_cents,
                'estimated_delivery' => $method->estimatedDeliveryLabel(),
            ]);

        return response()->json(['data' => $methods]);
    }
}
