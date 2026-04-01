<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Models\WaitlistSubscription;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

final class WaitlistController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        if ($product->stock?->isInStock()) {
            return response()->json(['message' => 'Product is already in stock.'], 422);
        }

        WaitlistSubscription::query()->firstOrCreate(
            ['product_id' => $product->id, 'email' => $validated['email'], 'tenant_id' => Context::get('tenant_id')],
            ['notified_at' => null],
        );

        return response()->json(['message' => 'You will be notified when this product is back in stock.'], 201);
    }
}
